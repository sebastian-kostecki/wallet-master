> **⚠️ Deprecated (2026-06-03):** Ten plan opisuje ręczne mapowanie kolumn i 2-etapowy UX z mapowaniem w UI. **Obowiązuje `.docs/prd.md`** (FR-I1, FR-I4: adapter-only, auto-commit). Implementacja: `PrepareImportUpload`, `CommitImport`, adaptery w `app/Imports/BankAdapters/`.

## FR summary
MVP import ma pozwalać użytkownikowi wczytać transakcje z CSV/XLSX do wybranego konta. Flow UX: **widok transakcji → Import → wybór konta → upload → mapowanie kolumn → auto-commit (bez preview) → wynik**.
System:
- parsuje i waliduje wiersze (data/kwota/opis; `subject` opcjonalny),
- wylicza `type` ze znaku kwoty, zapisuje kwoty ujemne/dodatnie,
- zawsze pomija duplikaty (po `date + amount + normalized_description` w obrębie konta),
- tworzy tylko nowe transakcje i aktualizuje `current_balance` konta jedną deltą po przetworzeniu pliku,
- zapisuje rekord `imports` z licznikami: `rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`.

Dodatkowo (MVP+ / Should): “pamięć” rozbijania surowego opisu z wyciągu na `subject` + `description` z wykorzystaniem Typesense:
- import próbuje wzbogacić `subject`/`description` na podstawie wcześniejszych edycji użytkownika,
- przy edycji transakcji po imporcie zapisujemy/upsertujemy pamięć, dzięki czemu kolejne importy mają lepsze dopasowania.

## Assumptions
- W MVP wspieramy 2 banki (np. BNP Paribas, mBank). Każdy bank ma osobną klasę adaptera/parsera.
- Bank importu wynika z `Account.bank` (użytkownik nie wybiera banku w imporcie).
- Commit importu jest wykonywany **asynchronicznie** w tle (Job), a status i wynik są dostarczane realtime (Reverb) z fallbackiem polling.
- Import jest procesem 2-etapowym technicznie (żeby obsłużyć mapowanie), ale UX nie ma “preview danych”:
  - etap A: upload + ekstrakcja nagłówków/kolumn,
  - etap B: commit z mapowaniem (auto-commit po zapisaniu mapowania w UI).

## Current codebase facts (do wykorzystania)
- Istnieje tabela i model `Import` (`database/migrations/2026_04_22_053133_create_imports_table.php`, `app/Models/Import.php`).
- Istnieje mechanizm deduplikacji transakcji poprzez `transactions.account_id + dedupe_hash` (unikat) i helper `App\Support\Transactions\TransactionDedupe` używany przez:
  - `app/Http/Requests/Transactions/StoreTransactionRequest.php`
  - `app/Actions/Transactions/StoreTransaction.php`
- Policy importu istnieje: `app/Policies/ImportPolicy.php` zawiera akcję `commit()` blokującą import do soft-deleted konta.
- Sail ma Typesense (`compose.yaml`), ale w codebase nie ma jeszcze warstwy klienta/integracji.

## Proposed backend architecture

### 1) Routes / endpoints (Inertia-friendly)
Wzorzec: podobnie jak `transactions` i `transfers` – klasyczny web POST/redirect + walidacja FormRequest.

- **GET** `imports` → `ImportController@index` (opcjonalnie: historia importów / debug / przyszły ekran)
- **POST** `imports/upload` → `ImportUploadController@store`
  - tworzy rekord `imports` w statusie `draft`
  - zapisuje plik tymczasowo (powiązany z `import_id`) i zwraca listę kolumn/nagłówków do mapowania
- **POST** `imports/{import}/commit` → `ImportCommitController@store`
  - przyjmuje mapowanie i uruchamia Job commitujący import w tle
  - blokuje ponowny commit tego samego importu (gdy status != `draft`)
  - zwraca “accepted” i redirect do listy transakcji; UI nasłuchuje statusu importu realtime
- **GET** `imports/{import}` → `ImportController@show`
  - zwraca status importu i (jeśli zakończony) podsumowanie `rows_*` oraz ewentualny `error_summary`
  - opcjonalnie zwraca skróconą listę błędnych/skipowanych pozycji tylko informacyjnie dla bieżącego importu (bez retencji pełnych surowych wierszy)

Nazewnictwo route (propozycja):
- `imports.index`
- `imports.upload`
- `imports.commit`
- `imports.show`

### 2) Import lifecycle / statuses
Wykorzystać `imports.status` (string):
- `draft`: po upload, przed commit
- `queued`: Job przyjęty do realizacji
- `processing`: Job w trakcie pracy **[Assumption]**
- `committed`: po sukcesie commit (`committed_at` ustawione)
- `failed`: błąd systemowy (np. nieudany odczyt pliku)

W `imports.error_summary` trzymać krótkie info (nie logować surowych wierszy).

### 3) Storage pliku (tymczasowy)
Po upload:
- zapisać plik w `storage/app/imports/{user_id}/{import_id}/source.{csv|xlsx}`
- zapisać metadane techniczne w `imports.details` (JSON), m.in. `source_file`, `parser`, `mapping_used`, `diagnostics`
Po commit:
- usunąć plik (albo trzymać przez krótki TTL; MVP: usuwać po sukcesie, zostawić po fail dla debug w dev). **[Assumption]**

### 4) Validation (FormRequests)
#### Upload request (`StoreImportUploadRequest`)
- `account_id`: `required|integer|exists:accounts,id` z warunkiem `user_id` i `deleted_at IS NULL`
- `file`: `required|file|mimes:csv,txt,xlsx` + limit rozmiaru (np. 10MB) **[Assumption]**

#### Commit request (`StoreImportCommitRequest`)
- autoryzacja: `ImportPolicy::commit($user, $import)` (defense-in-depth)
- `mapping`: required array / shape:
  - wymagane: `date`, `amount`, `description`; opcjonalne: `subject`
  - wartości mapowania wskazują nazwy nagłówków (headers są zawsze obecne)
- opcjonalnie: `save_mapping` boolean (czy zapisać profil mapowania per bank)

### 5) Parsing & bank adapters
Folder i kontrakt (propozycja):
- `app/Imports/BankAdapters/BankImportAdapter` (interface)
  - `bank(): Bank`
  - `readRows(UploadedFile|string $path): iterable<array<string,mixed>>` (CSV/XLSX → wiersze)
  - `detectCsvDelimiter(string $path): string` (autodetekcja separatora dla CSV)
  - `normalizeRow(array $row, array $mapping): ParsedImportRow`
  - `normalizeRawStatementDescription(string $raw): NormalizedStatementKeys` (strict/relaxed)
  - `extractSubjectAndDescription(string $raw, array $row): ExtractedFields` (opcjonalne reguły)
- Implementacje:
  - `BnpParibasImportAdapter`
  - `MBankImportAdapter`

Resolver:
- `BankImportAdapterResolver` wybiera adapter po `Account.bank`.

DTO:
- `ParsedImportRow`:
  - `date` (Y-m-d)
  - `amount` (numeric-string, z zachowaniem znaku)
  - `raw_statement_description` (oryginalny opis z wyciągu; klucz do “pamięci”)
  - `description` (wstępnie przetworzony lub raw; finalnie może zostać wzbogacony)
  - `subject` (nullable; finalnie może zostać wzbogacony)

### 6) Commit action (domain/service)
Akcja domenowa (propozycja):
- `app/Actions/Imports/CommitImport.php`
  - `handle(User $user, Import $import, array $mapping): ImportResult`

Algorytm:
1. `DB::transaction()` i `Account::lockForUpdate()` (żeby saldo było poprawne).
2. Odczyt pliku z path powiązanego z importem.
3. Inicjalizacja liczników i akumulatora `imported_amount_sum = 0`.
4. Dla każdego wiersza:
   - `rows_total++`
   - parse → `ParsedImportRow` (walidacja daty/kwoty i required fields)
   - dedupe:
     - `normalized_description = TransactionDedupe::normalizeDescription($finalDescription)`
     - `dedupe_hash = TransactionDedupe::dedupeHash($date, $amount, $normalized_description)`
     - jeśli istnieje w DB (unikat) → `rows_skipped_duplicate++`, continue
   - create `Transaction` z `import_id`
   - `imported_amount_sum += amount`
5. Jednorazowa aktualizacja salda: `account.current_balance += imported_amount_sum`.
6. `imports.status = committed`, `imports.mapping = mapping`, `imports.details` (JSON), liczniki, `committed_at`.
7. Zwrócić wynik: `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`.

Ważne:
- Nie przerywać całego importu przez pojedynczy zły wiersz: błędne wiersze liczymy do `rows_failed_validation` i pomijamy.
- Błędy systemowe (np. brak pliku) → `imports.status = failed`, `error_summary` ustawione.

### 6.5) Asynchroniczny commit (Job)
- `app/Jobs/CommitImportJob.php`:
  - bierze `import_id` i uruchamia `CommitImport`
  - ustawia statusy: `queued` → `processing` → `committed|failed`
  - retry: `tries = 3`, retry tylko dla błędów technicznych/infrastrukturalnych (nie dla błędów danych pliku)
  - w razie exception: `failed` + bezpieczny `error_summary` (bez danych wrażliwych)
  - import może być partial: jeśli wystąpi błąd krytyczny po zapisaniu części transakcji, zapisane rekordy pozostają w DB
- Idempotencja:
  - jeżeli `imports.committed_at` już ustawione → Job nic nie robi (guard)
  - transakcje są chronione unikalnym indeksem `transactions(account_id, dedupe_hash)` (duplikaty pomijamy)
  - jeżeli import nie jest w statusie `draft`, endpoint `commit` zwraca błąd (blokada ponownego commitu)

### 7) “Pamięć” `subject/description` w Typesense (Should)
Cel: dla `raw_statement_description` zapamiętać “jak user to poprawił” i użyć przy kolejnych importach.

#### Data needed in DB (żeby działało deterministycznie)
Transakcja musi pamiętać:
- `raw_statement_description` (text) – surowy opis z wyciągu jako **źródło prawdy** (nigdy nie nadpisywane)

To wymaga migracji tabeli `transactions` o co najmniej jedno pole (np. `raw_statement_description`).

Zasada zapisu:
- `transactions.subject` i `transactions.description` są tym, co widzi UI (po wzbogaceniu z Typesense/adapterów),
- `transactions.raw_statement_description` trzyma wersję oryginalną z wyciągu (do uczenia i debug).

#### Source of truth
W MVP “pamięć” jest przechowywana **wyłącznie w Typesense**. Źródłem danych do uczenia jest `transactions.raw_statement_description` (surowy opis z wyciągu) oraz finalne pola `transactions.subject` / `transactions.description` po edycji użytkownika.
Enrichment jest **best-effort**: brak Typesense lub brak dopasowania nie blokuje importu.

#### Typesense collection (propozycja)
Kolekcja: `import_description_memory`
Dokument:
- `id` (np. UUID albo hash)
- `user_id` (facet/filter)
- `bank` (facet/filter)
- `raw_key_strict` (string, exact)
- `raw_key_relaxed` (string, do fuzzy)
- `raw_original` (string, do debug)
- `learned_subject` (string)
- `learned_description` (string)
- `updated_at` (int)

#### Enrichment flow
Podczas importu (w adapterze lub osobnym serwisie):
- obliczyć `raw_key_strict/relaxed` z `raw_statement_description`,
- query Typesense filtrowane po `user_id` i `bank`,
- jeżeli hit powyżej progu → nadpisać `subject/description` na transakcji.

#### Learning flow (update memory)
Podczas update transakcji (w `UpdateTransaction` lub w `TransactionController@update`):
- jeżeli transakcja ma `import_id` (pochodzi z importu) i posiada `raw_statement_description`:
  - znormalizować klucze i wykonać upsert do Typesense (update/upsert) z `learned_subject/learned_description`

## Security / privacy
- Import i pamięć muszą być ściśle izolowane per user:
  - `imports.user_id` scoping,
  - Typesense query zawsze z filterem `user_id`.
- Nie logować surowych wierszy importu w prod (tylko agregaty + error_summary).

## Realtime status updates (MVP)
Cel: UI ma dowiedzieć się natychmiast, że import zakończył się sukcesem/porażką, bez agresywnego polling.

**Decision**: realtime (Reverb) jest częścią MVP. Polling zostaje jako fallback (rozłączenia, blokady sieci, itp.).

### Proposed approach (hybrid)
- `CommitImportJob` po zakończeniu:
  - aktualizuje `imports.status` (`committed|failed`) i liczniki `rows_*`,
  - emituje event domenowy:
    - `ImportCommitted` lub `ImportFailed` (payload minimalny: `import_id`, `user_id`, `status`, `rows_*`, `error_summary?`).
- Listener publikuje event realtime do kanału scoped per user, np. `imports.user.{userId}`.
- UI:
  - subskrybuje kanał po uruchomieniu importu,
  - po otrzymaniu eventu od razu pokazuje wynik,
  - jeśli event nie nadejdzie w czasie \(T\) lub połączenie padnie → kontynuuje polling do `imports.show`.

### Transport (implementation options)
- **Laravel Reverb** (wybrany transport): first-party WebSocket server dla Laravel (Pusher-protocol kompatybilny z Laravel Echo).
- Autoryzacja kanałów wymagana (użytkownik ma dostać tylko eventy własnych importów).

### Reverb installation & configuration (plan)
- Zainstalować broadcasting + Reverb scaffolding:
  - `php artisan install:broadcasting` (wybrać instalację Reverb w promptach) **lub** użyć wariantu z flagą, jeśli dostępny w danej wersji.
- Upewnić się, że aplikacja ma ustawione:
  - `BROADCAST_CONNECTION=reverb`
  - Reverb credentials w `.env` (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`)
  - server bind vars (jeśli używane): `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`
- Skonfigurować `config/reverb.php`:
  - `allowed_origins` (dev: `*`, prod: konkretne domeny)
- Uruchamianie serwera:
  - dev: `php artisan reverb:start`
  - prod: proces manager + `php artisan reverb:start` / `php artisan reverb:restart`
- Frontend:
  - installer zwykle konfiguruje Laravel Echo + `pusher-js` (Reverb używa Pusher protocol). Upewnić się, że klient subskrybuje kanał i odbiera event `import.updated`.

### Channel design (authorization)
- Preferowany kanał prywatny scoped per user:
  - `private-imports.user.{userId}`
- `routes/channels.php`:
  - autoryzacja: `return $user->id === (int) $userId;`
  - brak payloadów w autoryzacji poza `userId`

### Minimal event contract (example)
- Event name: `import.updated`
- Payload:
  - `import_id` (int)
  - `status` (`committed|failed`)
  - `rows_total` (int)
  - `rows_imported` (int)
  - `rows_skipped_duplicate` (int)
  - `rows_failed_validation` (int)
  - `error_summary` (string|null)

### Broadcasting implementation detail (decision)
- Eventy realtime implementujemy jako `ShouldBroadcast` (broadcasting przez kolejkę), a nie `ShouldBroadcastNow`.

## Testing plan (Pest)
- `tests/Feature/Imports/ImportUploadTest.php`
  - upload dla własnego konta → tworzy `imports.draft`, zwraca kolumny
  - upload dla cudzego konta / konta usuniętego → 422/403
- `tests/Feature/Imports/ImportCommitQueuedTest.php`
  - commit uruchamia Job i ustawia status `queued`
- `tests/Feature/Imports/CommitImportJobTest.php`
  - happy path: plik z kilkoma wierszami → tworzy transakcje, aktualizuje saldo, liczniki `rows_*`, status `committed`
  - dedupe: ten sam plik 2x → 2. import ma `rows_imported=0`, `rows_skipped_duplicate>0`
  - błędne wiersze: zła data/kwota → `rows_failed_validation` rośnie, reszta importuje się
  - autoryzacja: commit importu innego usera → 403 (na endpointzie)
- Typesense (jeśli włączone w testach):
  - testy integracyjne jako opcjonalne (feature flag / fake client), żeby nie flakowały CI **[Assumption]**

## Open questions (blokujące decyzje implementacyjne)
Brak pytań blokujących (decyzje domknięte).

## Defaults (MVP)
- UI po uruchomieniu importu:
  - nasłuchuje eventu `import.updated` (Reverb),
  - robi fallback polling do `imports.show` **co 2s przez 60s**,
  - po 60s pokazuje komunikat “Import nadal trwa” + przycisk “Odśwież”.


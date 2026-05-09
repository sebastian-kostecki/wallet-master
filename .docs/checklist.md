## Checklist implementacyjna (MVP) — Wallet Master

Cel: zrealizować zakres z `.docs/prd.md` (terminologia: **Konto** / **Transakcja** / **Import** / **Transfer**).

> **Uwaga.** Nowe zadania wynikające z `.docs/improvement-plan.md` są oznaczone tagiem `[plan]` przy nazwie sekcji lub punktu. Sekcje 12–17 zostały dopisane na podstawie planu poprawek.

---

### 0) Uruchomienie projektu / baseline
- [ ] Uruchom aplikację lokalnie (Sail albo `composer run dev`) i potwierdź, że Inertia + Vue działa.
- [ ] Ustal konwencję nazw: “Transakcja” (UI) / `Transaction` (model/tabela) / “Konto” / `Account`.
- [ ] Ustal formaty prezentacji:
  - [ ] Data prezentowana jako **DD-MM-YYYY**
  - [ ] Kwoty formatowane pod PL (przecinek dziesiętny w UI)

---

### 1) Model danych + migracje
- [x] Dodać encję waluty (MVP: rekord `PLN`), żeby w przyszłości dodać kolejne waluty.
- [x] Dodać encję konta:
  - [x] `name`
  - [x] `currency_id`
  - [x] `opening_balance` (saldo początkowe)
  - [x] `current_balance` (saldo bieżące — aktualizowane)
  - [x] `type` (enum w PHP + walidacja; DB: string)
  - [x] `bank` (enum w PHP + walidacja; DB: string)
  - [x] (decyzja) DB-level enum nie jest wymagany na MVP: zostajemy przy `string` w DB + `Rule::enum(...)` + casty do PHP enumów
  - [x] soft delete (żeby po “usunięciu konta” transakcje zostawały, ale były read-only)
- [x] Dodać encję transakcji:
  - [x] `account_id`, `user_id` (lub inny jednoznaczny mechanizm izolacji)
  - [x] `date` (data operacji, bez czasu)
  - [ ] `booked_at` (data przypisania do okresu rozliczeniowego, default = `date`) **[plan §1]**
  - [x] `amount` jako **decimal** (ujemne dla wydatków, dodatnie dla przychodów)
  - [~] `type` (`income` / `expense` / `transfer` / **`adjustment`**) — kolumna do rozszerzenia z `varchar(10)` na `varchar(20)` **[plan §3]**
  - [x] `description`
  - [x] `subject` (nadawca/odbiorca)
  - [x] `currency_id` (na MVP zawsze PLN, ale pole istnieje)
  - [x] `transfer_id` (nullable; łączy 2 transakcje transferu)
  - [ ] `transfer_match_status` (`none` / `auto` / `manual` / `rejected`) **[plan §4]**
  - [ ] `transfer_candidate_for_id` (FK na `transactions.id`, nullable) **[plan §4]**
  - [ ] `bank_reference_id` (nullable; główny klucz dedupe gdy bank go dostarcza) **[plan §2]**
  - [x] `import_id` (nullable; powiązanie z importem)
  - [x] `raw_statement_description` (surowy opis z wyciągu)
- [x] Dodać encje importu + mapowania per bank:
  - [x] `imports`: `user_id`, `account_id`, status, liczniki (`rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`)
  - [x] `imports.details` (JSON): metadane techniczne (`mapping_used`, `source_file`, `parser`, `diagnostics`)
  - [x] `import_profiles` (per user + bank): zapis mapowania kolumn + wersjonowanie (opcjonalnie) — na MVP mapowanie trzymamy w `imports.mapping` (JSON), bez osobnej tabeli
- [~] Indeksy:
  - [x] `transactions(account_id, date)` (zachowany dla zapytań po dacie operacji)
  - [ ] `transactions(account_id, booked_at)` **[plan §1]**
  - [ ] `transactions(user_id, booked_at)` **[plan §1]**
  - [x] `transactions(transfer_id)`
  - [ ] Drop dotychczasowego unique `transactions(account_id, dedupe_hash)` i zamiana na: **[plan §2]**
    - [ ] unique `transactions(account_id, bank_reference_id)` (nullable)
    - [ ] non-unique index `transactions(account_id, dedupe_hash)` (fallback)

---

### 2) Autoryzacja i izolacja danych
- [ ] Zaimplementować autoryzację per zasób (konto/transakcja/import) tak, aby użytkownik widział wyłącznie swoje dane.
- [ ] Dodać testy izolacji danych (min. 2 użytkowników, próby odczytu/edycji cudzych zasobów).
- [ ] Upewnić się, że reset hasła nie ujawnia czy email istnieje.

---

### 3) Konta — API/CRUD + UI
- [x] Lista kont (z walutą i bieżącym saldem).
- [x] Dodanie konta:
  - [x] Walidacje: nazwa wymagana; saldo początkowe liczba; waluta wybieralna, ale na MVP dostępna tylko PLN.
  - [x] Walidacje: `bank` i `type` wymagane i ograniczone do listy (enum).
- [x] Edycja konta:
  - [x] Zmiana nazwy, salda początkowego.
  - [x] Zmiana `bank` i `type` + konsekwencje dla importu (bank wynika z konta) — do uwzględnienia, gdy import będzie wdrażany.
  - [x] Zdefiniować wpływ zmiany salda początkowego na `current_balance` (rekomendacja: przeliczyć różnicą). 
- [x] Usuwanie konta:
  - [x] Soft delete konta.
  - [~] Zablokować edycję/usuwanie transakcji na usuniętym koncie:
    - [x] backend (policy)
    - [x] UI (blokady/ukrycie akcji)
  - [~] Zablokować import i transfer dla usuniętego konta:
    - [x] backend: częściowo (Import policy dla commit)
    - [ ] backend: brak tras/flow importu i transferu → do wdrożenia wraz z modułami
    - [ ] UI (blokady/CTA/disabled)
- [~] Korekta salda:
  - [x] Akcja „Ustaw saldo" (manual adjustment).
  - [x] Audit trail minimalny (kto/kiedy/stara→nowa wartość) w `account_balance_adjustments`.
  - [ ] **Zmiana implementacji:** korekta tworzy transakcję typu `adjustment` z `amount = newBalance - currentBalance`; saldo aktualizowane przez normalną sumę transakcji **[plan §3]**.
  - [ ] Komenda `php artisan accounts:recalculate-balance {account?} [--all] [--dry-run]` **[plan §3]**.
- [ ] Audyt usunięcia konta — tabela `account_deletions` (kto/kiedy/liczba transakcji w momencie usunięcia) **[plan §12.7]**.

---

### 4) Transakcje — API/CRUD + UI
- [~] Lista transakcji:
  - [x] Filtry: konto, zakres dat
  - [ ] **Filtry dat działają po `booked_at`** (nie po `date`) **[plan §1]**
  - [x] Sort: data/kwota
  - [ ] Sort `date` → wewnętrznie sortuje po `booked_at desc, date desc, id desc` **[plan §1, §12.6]**
  - [x] Paginacja (backend)
  - [x] Podsumowanie zakresu: suma wpływów i wydatków (oddzielnie)
  - [ ] **Wykluczenie wewnętrznych transferów** z `summary` (`transfer_id IS NULL`) **[plan §12.2]**
  - [x] Empty state + CTA
  - [ ] Kolumna „Okres rozliczeniowy" obok „Data operacji" **[plan §1]**
  - [ ] Badge „Korekta" dla transakcji typu `adjustment` **[plan §3]**
- [~] Dodanie transakcji:
  - [x] Pola: data (DD-MM-YYYY), kwota (decimal), opis, subject (opcjonalny)
  - [ ] Pole `booked_at` (DD-MM-YYYY) z domyślną wartością równą `date` **[plan §1]**
  - [x] Ustalenie typu na podstawie znaku kwoty (ujemna=wydatek, dodatnia=przychód)
  - [ ] Egzekwowanie `amount != 0` w warstwie domeny przez enum `TransactionType::fromAmount` **[plan §6]**
  - [x] Walidacje: kwota != 0 (FormRequest); konto nieusunięte
  - [x] Aktualizacja `current_balance` konta deltą kwoty
  - [ ] **Usunięcie blokady „A similar transaction already exists"** w `StoreTransactionRequest` / `UpdateTransactionRequest` — manualne duplikaty są dozwolone **[plan §2]**
- [~] Edycja transakcji:
  - [x] Zmiana pól i przeliczenie delty salda (stara kwota → nowa kwota)
  - [x] Blokada edycji dla transakcji na usuniętym koncie
  - [ ] Edycja `booked_at` niezależnie od `date` (nie wpływa na saldo, tylko na okres) **[plan §1]**
  - [ ] Edycja transakcji typu `transfer` (z ustawionym `transfer_id`) — kwota tylko po „Rozłącz transfer" **[plan §4]**
- [~] Usuwanie transakcji:
  - [x] Aktualizacja salda deltą (odwrócenie wpływu)
  - [x] Blokada usuwania dla transakcji na usuniętym koncie
  - [ ] UI: akcja usuwania (np. na ekranie edycji lub w tabeli)

---

### 5) Transfer — jedna akcja → 2 transakcje
- [~] UI “Transfer”:
  - [x] Konto źródłowe != konto docelowe
  - [x] Użytkownik wpisuje kwotę dodatnią, system zapisuje `-X` i `+X` (walidacja wejścia)
  - [x] Data wspólna
  - [ ] Opis (wspólny) + subject (opcjonalny) — brak pola `subject` w UI; backend zapisuje `subject = null`
- [x] Backend:
  - [x] Utworzyć 2 transakcje w jednej transakcji DB
  - [x] Ustawić `transfer_id` (wspólny identyfikator)
  - [x] Zaktualizować saldo obu kont
- [x] Blokady:
  - [x] Transfer do/z usuniętego konta niedozwolony

---

### 6) Import — flow: z widoku transakcji → wybór konta → upload → mapowanie → auto-commit → wynik

#### 6.1 Kontrakty i UI flow
- [x] Entry point: przycisk “Import” na widoku transakcji (modal/wizard).
- [x] Modal krok 1: wybór konta (bank wynika z konta i jest wyświetlany).
- [x] Modal krok 2: upload CSV/XLSX.
- [ ] Modal krok 3: mapowanie kolumn:
  - [ ] Wymagane: data, kwota, opis
  - [ ] Opcjonalne: `subject`
  - [ ] Mapowanie po nazwach nagłówków (headers zawsze obecne)
  - [ ] Zapisywanie mapowania per user + bank konta (profil) i automatyczne podpowiadanie
- [ ] Auto-commit importu (bez preview):
  - [x] Walidacja + dedupe + zapis w jednej akcji
  - [x] Blokada ponownego commitu tego samego importu (gdy status != `draft`)
  - [x] Podsumowanie końcowe: `rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`
- [ ] Widok wyniku:
  - [ ] Po commit redirect do strony transakcji
  - [ ] Informacyjna lista błędnych/skipowanych pozycji dla bieżącego importu (bez retencji pełnych surowych wierszy)

#### 6.2 Parsowanie i walidacja
- [~] CSV:
  - [x] Autodetekcja separatora
  - [~] Obsłużyć kwoty z `,` i `.` (wejście) — **wymagana wymiana parsera na `App\Support\Imports\AmountParser`** (separator tysięcy `.`/spacja/NBSP, nawiasy księgowe, przyrostki walut) **[plan §5.2]**
- [ ] **Detekcja kodowania pliku** (UTF-8 / Windows-1250 / ISO-8859-2) z konwersją do UTF-8 + usunięcie BOM (`App\Support\Imports\FileEncodingNormalizer`) **[plan §5.1]**
- [x] XLSX:
  - [x] Importować pierwszy arkusz
- [~] Data:
  - [x] Parsowanie do formatu daty (prezentacja DD-MM-YYYY; storage jako date)
  - [ ] Wymiana parsera na `App\Support\Imports\DateParser` z obsługą `d-m-Y`, `Y-m-d`, `d/m/Y`, `d.m.Y`, `Y.m.d`, `Y/m/d` + odcinanie suffixu czasu **[plan §5.3]**
- [~] Kwota:
  - [x] Ujemne kwoty → wydatek, dodatnie → przychód (ustawić `type`)
  - [ ] **Kwota 0 → błąd walidacji**: egzekwowane przez `TransactionType::fromAmount` (rzucanie `DomainException`) → `rows_failed_validation++` **[plan §6]**
- [~] `subject`:
  - [~] Ekstrakcja per bank (adaptery) z `description` lub innych pól, jeśli bank tego wymaga (na MVP: `subject` z kolumny mapowania, jeśli istnieje)
  - [ ] **Usunąć mapowanie `Kategoria → subject` w `MBankImportAdapter`** (semantycznie niepoprawne) **[plan §12.1]**
  - [x] Fallback: pozostawić puste, jeśli nie da się wyciągnąć **[Assumption]**
- [ ] **`bank_reference_id`** — dodać do `ParsedImportRow` i wyciągać w adapterach z odpowiedniej kolumny wyciągu (mBank: „Numer referencyjny" / „Identyfikator transakcji"; BNP: „Numer referencyjny" / „Numer transakcji") **[plan §2]**

#### 6.3 Deduplikacja (zawsze pomijamy)
- [~] Zaimplementować klucze dedupe v2 **[plan §2]**:
  - [ ] **Główny**: `bank_reference_id` na tym samym `account_id` (gdy nie-null)
  - [x] **Fallback** (`bank_reference_id` null): `date + amount + normalized_description` na tym samym `account_id`
- [x] Zaimplementować normalizację opisu (fallback):
  - [x] `trim`
  - [x] `case-fold` (np. lowercase)
  - [x] standaryzacja whitespace (wielokrotne spacje → jedna)
- [x] Import używa tej samej logiki dedupe niezależnie od banku (adapter może dostarczyć wstępnie oczyszczony opis).
- [ ] Bezpieczeństwo: dwa zakupy w tym samym sklepie tego samego dnia z różnymi `bank_reference_id` **muszą** być oba zaimportowane. Test pokrywający ten scenariusz **[plan §2]**.

#### 6.4 Salda po imporcie + chunked processing
- [x] Agregować sumę kwot tylko dla faktycznie utworzonych transakcji (`imported_amount_sum`).
- [x] Wykonać jedną aktualizację `current_balance` po przetworzeniu importu.
- [x] Zabezpieczyć przed podwójnym zapisem (idempotencja importu przez `import_id` + dedupe).
- [ ] **Chunked processing**: pętla wierszy w batchach po 500, każdy chunk w osobnej krótkiej `DB::transaction`; bulk insert; lock konta tylko w finalnej transakcji aktualizującej saldo **[plan §7]**.
- [ ] Importer odporny na `Lock wait timeout` przy pliku 5000+ wierszy (testowane).

#### 6.5a Realtime status importu (MVP)
- [x] Włączyć aktualizację statusu importu przez Reverb (`queued` → `committed|failed`).
- [ ] **Broadcast po przejściu na `processing`** (obecnie pomijane) **[plan §8]**.
- [ ] **Broadcast progresu po każdym chunk** (`rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`) **[plan §8]**.
- [ ] **Polling jako fallback w UI** (co 5s, gdy WebSocket rozłączony) — `useImportStatus` composable **[plan §8]**.
- [x] Event `import.updated` z payloadem statusu i liczników `rows_*`.
- [ ] Payload eventu rozszerzony o `progress` (struktura jak liczniki) **[plan §8]**.

#### 6.5 Enrichment `subject`/`description` z „pamięci" (Typesense)
- [x] Dodać „surowy opis z wyciągu" do transakcji/importu (np. `raw_statement_description`) i przechowywać go dla transakcji z importu.
- [x] Typesense collection „pamięci" (per user + bank):
  - [x] Klucze normalizacyjne (strict/relaxed) dla `raw_statement_description`
  - [x] Przechowywane wartości: `learned_subject`, `learned_description`, `updated_at`
- [x] Import: dla każdej transakcji spróbować dopasować pamięć po `raw_statement_description` i auto-uzupełnić `subject`/`description` (best-effort; brak trafienia lub brak Typesense → fallback).
- [x] Edycja transakcji: jeżeli transakcja pochodzi z importu i user zmieni `subject` i/lub `description`, wykonać upsert do pamięci w Typesense.
- [x] Izolacja danych: pamięć musi być ściśle per user (bez możliwości dopasowań między użytkownikami).

#### 6.6 Identyfikacja transferów podczas importu (matcher) [plan §4]
- [ ] Klasa `App\Imports\TransferMatcher` z metodą `matchAfterImport(Import $import): void`.
- [ ] Heurystyki dopasowania (kolejność):
  - [ ] **probable** (auto-link): inne `account_id`, te same `user_id` i waluta, przeciwne znaki kwot, identyczne `|amount|`, `|date_a − date_b| ≤ 3 dni`, opisy zawierają któryś z tokenów konfiguracyjnych (`przelew własny`, `przelew wewn`, `transfer`, `własny`, `between accounts`).
  - [ ] **manual** (do potwierdzenia): jak wyżej, ale bez tokena „transfer" w opisie.
  - [ ] **ambiguous → manual**: gdy >1 kandydatka — żaden z wpisów nie jest auto-linkowany.
- [ ] Lista tokenów w `config/imports.php` (`transfer_tokens`).
- [ ] Auto-link: ustawia wspólny `transfer_id = (string) Str::uuid()`, oba `transfer_match_status = 'auto'`, oba `type = 'transfer'`. **Bez ponownej aktualizacji salda** (kwoty już zaksięgowane).
- [ ] Manual link: ustawia obu transakcjom wzajemny `transfer_candidate_for_id` + `transfer_match_status = 'manual'`.
- [ ] Wywołanie `TransferMatcher::matchAfterImport` w `CommitImport::handle` po pętli wierszy, przed broadcastem `Committed`.
- [ ] Endpoint `GET /transfers/candidates` + Vue strona „Możliwe transfery" (sekcja menu z badge'em).
- [ ] Akcje:
  - [ ] „Potwierdź transfer" (`POST /transfers/candidates/{id}/confirm`) — ustawia wspólny `transfer_id`, `type=transfer`, `transfer_match_status='manual'`.
  - [ ] „To nie transfer" (`POST /transfers/candidates/{id}/reject`) — `transfer_match_status='rejected'` na obu, czyści `transfer_candidate_for_id`.
- [ ] Akcja „Rozłącz transfer" (`POST /transfers/{transferId}/unlink`) — czyści `transfer_id` na obu nogach, przywraca `type` na podstawie znaku `amount`, status `rejected`.
- [ ] Telemetria: `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`.
- [ ] Walidacja waluty: różne waluty → brak dopasowania (na MVP).

---

### 7) Adaptery banków + profile mapowań
- [x] Zdefiniować listę banków (enum/slug): `BnpParibas`, `MBank`, `Cash` (Gotówka).
- [ ] Ikony banków:
  - [ ] Dodać assets dla `BnpParibas`, `MBank`, `Cash`
  - [ ] Mapowanie ikon po slugu `bank` w UI
- [x] Zdefiniować interfejs “BankAdapter”:
  - [ ] identyfikator banku (`bank_key`)
  - [~] ekstrakcja `subject` (na MVP: kolumna `subject`, jeśli istnieje; bez reguł ekstrakcji z `description`)
  - [ ] ewentualne pre-processing `description` (tylko jeśli wymagane)
- [~] Mechanizm rejestracji adapterów + fallback “Generic”. (jest resolver, fallback = wyjątek)
- [x] Resolver adaptera po `Account.bank` (bank nie wybierany osobno w imporcie).
- [x] Implementacje adapterów (MVP):
  - [x] `BnpParibas`
  - [x] `MBank`
  - [x] `Cash` (konto gotówkowe; import niewspierany lub ograniczony — decyzja produktowa/techniczna)
- [ ] Model `ImportMapping` per user + bank + format_fingerprint **[plan §11]**:
  - [ ] tabela `import_mappings` już istnieje — dodać unique `(user_id, bank, format_fingerprint)`
  - [ ] `format_fingerprint = sha1(implode('|', headers))`
  - [ ] `PrepareImportUpload`: jeżeli istnieje pasujący `ImportMapping` → użyj jego `mapping`; w przeciwnym razie fallback do `adapter->defaultMapping`
  - [ ] `QueueImportCommit`: `updateOrCreate` po sukcesie + emit `import_mapping_saved`/`import_mapping_reused`
  - [ ] UI w modalu importu: krok „Mapowanie kolumn" z dropdownami per pole + checkbox „Zapisz to mapowanie"

---

### 8) Telemetria / eventy (min.)
- [ ] Auth: `user_registered`, `user_logged_in`, `user_login_failed`
- [ ] Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`
- [ ] Transakcje: `transaction_create_opened`, `transaction_created`, `transaction_updated`, `transaction_deleted`
- [ ] Lista: `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`
- [ ] Transfer: `transfer_created`, `transfer_failed_validation`
- [~] Import: `import_started`, `import_completed`, `import_failed`, `import_mapping_saved`, `import_mapping_reused`, `import_type_inferred`, `import_bank_resolved_from_account` (opcjonalnie), `import.updated` (realtime)

---

### 9) UX jakościowe (do odhaczenia)
- [ ] Wszystkie formularze mają:
  - [ ] walidację inline
  - [ ] czytelne komunikaty błędów po polsku
  - [ ] stany loading/disabled podczas requestów
- [ ] Empty states (konta/transakcje/import) mają jasne CTA.
- [ ] A11y:
  - [ ] pełna obsługa klawiaturą
  - [ ] focus states widoczne
  - [ ] poprawne `label`/aria
  - [ ] kontrast WCAG AA

---

### 10) Testy (Pest) + QA checklist

#### 10.1 Testy automatyczne (minimum)
- [ ] Autoryzacja i izolacja danych (konto/transakcja/import).
- [ ] CRUD kont:
  - [ ] tworzenie/edycja/usunięcie (soft delete)
  - [ ] transakcje na usuniętym koncie są read-only
- [ ] CRUD transakcji:
  - [ ] saldo aktualizuje się o deltę kwoty przy create/update/delete
- [ ] Transfer:
  - [ ] tworzy 2 transakcje z `transfer_id`
  - [ ] aktualizuje saldo obu kont
- [ ] Import:
  - [ ] walidacja wymaganych pól
  - [ ] deduplikacja po normalizacji opisu
  - [ ] liczniki `rows_*` poprawne
  - [ ] blokada ponownego commitu tego samego importu
  - [ ] retry joba importu: 3 próby tylko dla błędów technicznych
  - [ ] partial import: zapisane rekordy pozostają przy błędzie krytycznym

#### 10.2 Manual QA (minimum)
- [ ] “Happy path” rejestracja → konto → transakcja → filtr → import → transfer.
- [ ] Import: plik z samymi duplikatami → komunikat i `rows_imported=0`.
- [ ] Usunięte konto:
  - [ ] transakcje widoczne
  - [ ] brak możliwości edycji/usuwania
  - [ ] brak możliwości importu/transferu

---

### 11) Quality gates przed merge
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact` (uruchomić testy dotknięte zmianami)
- [ ] `vendor/bin/phpstan analyse` (Larastan) bez nowych ostrzeżeń
- [ ] `npm run lint` + `npm run format` (jeśli dotyczy)
- [ ] Brak logowania wrażliwych danych importu w produkcji (przegląd logów/handlerów).

---

### 12) Bezpieczeństwo i UX poprawki **[plan §9]**

#### 12.1 Rate limiting
- [ ] `RateLimiter::for('imports', ...)` w `AppServiceProvider::boot` — 10/min per `user_id` (fallback per IP).
- [ ] `RateLimiter::for('api', ...)` — 60/min per zalogowanego użytkownika.
- [ ] Middleware `throttle:imports` na trasach `imports/upload` i `imports/{import}/commit` w `routes/web.php`.

#### 12.2 Konto Cash bez 500
- [ ] `PrepareImportUpload::execute` — zamiast `RuntimeException` zwraca `PrepareImportUploadResult::importsNotSupported(...)`.
- [ ] `TransactionImportController::upload` — mapowanie na 422 z `message_key = imports.errors.bank_unsupported`.
- [ ] UI: czytelny toast „Konta gotówkowe nie obsługują importu z pliku".

#### 12.3 Mass assignment
- [ ] Modele `Account`, `Transaction`, `Import`, `AccountBalanceAdjustment` — `$fillable` zamiast `$guarded = []`.
- [ ] `Model::shouldBeStrict()` w `AppServiceProvider::boot()` (poza prod).

---

### 13) Telemetria **[plan §10]**

- [ ] Klasa `App\Telemetry\Event::record(string $name, array $payload, ?int $userId = null)`.
- [ ] Kanał loga `telemetry` w `config/logging.php` (daily, JSON line).
- [ ] Wywołania w warstwie domeny / akcji:
  - [ ] Auth: `user_registered`, `user_logged_in`, `user_login_failed`
  - [ ] Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`
  - [ ] Transakcje: `transaction_created`, `transaction_updated`, `transaction_deleted`
  - [ ] Lista (front-end POST `/telemetry/event`, throttle 60/min): `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`, `transaction_create_opened`
  - [ ] Transfer: `transfer_created`, `transfer_failed_validation`, `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`
  - [ ] Import: `import_started`, `import_completed`, `import_failed`, `import_mapping_saved`, `import_mapping_reused`, `import_type_inferred`, `import_bank_resolved_from_account`, `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss`
- [ ] Helper front-end `resources/js/lib/telemetry.ts` z `track(name, payload)`.

---

### 14) Komendy artisan i scheduler **[plan §3, §12.4]**

- [ ] `php artisan accounts:recalculate-balance {account?} [--all] [--dry-run]`
- [ ] `php artisan imports:purge-old-files {--days=30}` — czyści pliki importów `Failed` starszych niż N dni.
- [ ] Wpis w `bootstrap/app.php` lub `routes/console.php` — uruchamianie `imports:purge-old-files` codziennie.

---

### 15) Retencja plików importu **[plan §12.4]**

- [ ] `CommitImport::handle` na sukces: usuwa plik źródłowy (jak teraz).
- [ ] `CommitImport::handle` na `Failed`: zachowuje plik w `storage/app/imports/{user}/{import}/source-failed.{ext}`.
- [ ] Komenda `imports:purge-old-files` (sekcja 14) sprząta po 30 dniach.

---

### 16) Testy izolacji danych **[plan §13]**

- [ ] `tests/Feature/Authorization/AccountIsolationTest.php` — 4 metody × 4 scenariusze (view/store/update/destroy) między dwoma userami.
- [ ] `tests/Feature/Authorization/TransactionIsolationTest.php` — analogicznie.
- [ ] `tests/Feature/Authorization/ImportIsolationTest.php` — analogicznie (view, commit).
- [ ] `tests/Feature/Imports/TypesenseMemoryIsolationTest.php` — pamięć usera A nie wpływa na sugestie usera B.

---

### 17) Drobne, ale ważne **[plan §12]**

- [ ] `TransactionImportController::index` — `paginate(20)` zamiast `latest()->limit(30)`.
- [ ] Sortowanie listy transakcji po `amount` z tie-breakerem `booked_at desc, id desc` (po wdrożeniu `booked_at`).
- [ ] Usunięcie `Kategoria → subject` z `MBankImportAdapter::defaultMapping`.
- [ ] Wymagany wpis audytowy w `account_deletions` przy `AccountController::destroy`.


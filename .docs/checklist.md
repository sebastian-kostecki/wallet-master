## Checklist implementacyjna (MVP) — Wallet Master

Cel: zrealizować zakres z `.docs/prd.md` (terminologia: **Konto** / **Transakcja** / **Import** / **Transfer**).

> **Uwaga.** Nowe zadania wynikające z `.docs/improvement-plan.md` są oznaczone tagiem `[plan]` przy nazwie sekcji lub punktu. Sekcje 12–17 zostały dopisane na podstawie planu poprawek.

> **Ostatnia synchronizacja:** 2026-06-02 (branch `improvement/transactions`). Refaktoryzacja architektury (fazy 0–5): `.docs/refactoring.md` §10 — **zakończona**. Specyfikacje i plany Superpowers: `.docs/superpowers/`.

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
  - [x] `booked_at` (data przypisania do okresu rozliczeniowego, default = `date`) **[plan §1]**
  - [x] `amount` jako **decimal** (ujemne dla wydatków, dodatnie dla przychodów)
  - [x] `type` (`income` / `expense` / `transfer` / **`adjustment`**) — `varchar(20)` (MySQL); SQLite bez migracji długości **[plan §3]**
  - [x] `description`
  - [x] `subject` (nadawca/odbiorca)
  - [x] `currency_id` (na MVP zawsze PLN, ale pole istnieje)
  - [x] `transfer_id` (nullable; łączy 2 transakcje transferu)
  - [x] `transfer_match_status` (`none` / `auto` / `manual` / `rejected`) **[plan §4]**
  - [x] `transfer_candidate_for_id` (FK na `transactions.id`, nullable) **[plan §4]**
  - [x] `import_id` (nullable; powiązanie z importem)
  - [x] `raw_statement_description` (surowy opis z wyciągu)
- [x] Dodać encje importu + mapowania per bank:
  - [x] `imports`: `user_id`, `account_id`, status, liczniki (`rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`)
  - [x] `imports.details` (JSON): metadane techniczne (`mapping_used`, `source_file`, `parser`, `diagnostics`)
  - [x] `import_profiles` (per user + bank): zapis mapowania kolumn + wersjonowanie (opcjonalnie) — na MVP mapowanie trzymamy w `imports.mapping` (JSON), bez osobnej tabeli
- [x] Indeksy:
  - [x] `transactions(account_id, booked_at)` **[plan §1]** (indeksy po `date` usunięte na MySQL po migracji)
  - [x] `transactions(user_id, booked_at)` **[plan §1]**
  - [x] `transactions(transfer_id)`
  - [ ] Zmiana unique `transactions(account_id, dedupe_hash)` → non-unique index (manualne duplikaty dozwolone) **[plan §2]**

---

### 2) Autoryzacja i izolacja danych
- [x] Zaimplementować autoryzację per zasób (konto/transakcja/import) — Policies (`Account`, `Transaction`, `Import`, `ImportFailedRow`).
- [~] Dodać testy izolacji danych (min. 2 użytkowników, próby odczytu/edycji cudzych zasobów):
  - [x] `tests/Feature/Transactions/TransactionAuthorizationTest.php` (create/edit/store/update/destroy między userami)
  - [ ] pełny pakiet `tests/Feature/Authorization/*IsolationTest.php` — sekcja 16
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
  - [x] Zablokować import i transfer dla usuniętego konta:
    - [x] backend (policies + middleware konta aktywnego)
    - [x] UI (blokady/CTA/disabled na liście transakcji i formularzach)
- [~] Korekta salda:
  - [x] Akcja „Ustaw saldo" (manual adjustment).
  - [x] Audit trail minimalny (kto/kiedy/stara→nowa wartość) w `account_balance_adjustments`.
  - [x] **Zmiana implementacji:** korekta tworzy transakcję typu `adjustment` z `amount = newBalance - currentBalance`; saldo aktualizowane tak samo jak dotychczas (zapis `current_balance` po delcie) **[plan §3]**.
  - [x] Komenda `php artisan accounts:recalculate-balance {account?} [--all] [--dry-run]` **[plan §3]**.
- [ ] Audyt usunięcia konta — tabela `account_deletions` (kto/kiedy/liczba transakcji w momencie usunięcia) **[plan §12.6]**.

---

### 4) Transakcje — API/CRUD + UI
- [~] Lista transakcji:
  - [x] Filtry: konto, zakres dat
  - [x] **Filtry dat po `COALESCE(booked_at, date)`** (wyświetlana data okresu) **[plan §1]**
  - [x] **Persistencja filtrów/sortu/strony** w sesji + redirect (`TransactionsIndexQuery`) **[branch 2026-06]**
  - [x] Sort: data/kwota
  - [x] Sort `date` → `COALESCE(booked_at, date)` + tie-breaker `date desc, id desc` **[plan §1, §12.5]**
  - [x] Paginacja (backend)
  - [x] Podsumowanie zakresu: suma wpływów i wydatków (oddzielnie)
  - [ ] **Wykluczenie wewnętrznych transferów** z `summary` (`transfer_id IS NULL`) **[plan §12.2]**
  - [x] Empty state + CTA
  - [~] Kolumna „Okres rozliczeniowy" obok „Data operacji" — jedna kolumna z datą `booked_at ?? date` + tooltip surowego opisu wyciągu **[plan §1, branch 2026-06]**
  - [x] Badge „Korekta" dla transakcji typu `adjustment` **[plan §3]**
- [~] Dodanie transakcji:
  - [x] Pola: data (DD-MM-YYYY), kwota (decimal), opis, subject (opcjonalny)
  - [x] Pole `booked_at` (DD-MM-YYYY) z domyślną wartością równą `date` (Create/Edit) **[plan §1]**
  - [x] Ustalenie typu na podstawie znaku kwoty (ujemna=wydatek, dodatnia=przychód)
  - [~] Egzekwowanie `amount != 0` w warstwie domeny przez enum `TransactionType::fromAmount` (Store/Update manual + wiersze importu) **[plan §3, §6]**
  - [x] Walidacje: kwota != 0 (FormRequest); konto nieusunięte
  - [x] Aktualizacja `current_balance` konta deltą kwoty
  - [ ] **Usunięcie blokady „A similar transaction already exists"** w `StoreTransactionRequest` / `UpdateTransactionRequest` — manualne duplikaty są dozwolone **[plan §2]**
  - [ ] Endpoint `GET /transactions/duplicate-check` (account_id, date, amount, description) → `{ exists, sample? }` **[plan §2]**
  - [ ] UI w `Create.vue` / `Edit.vue`: debounced preflight + inline ostrzeżenie „Wykryto podobną transakcję — dodać mimo to?" + flaga `confirmed_duplicate` w payloadzie do telemetrii **[plan §2]**
- [~] Edycja transakcji:
  - [x] Zmiana pól i przeliczenie delty salda (stara kwota → nowa kwota)
  - [x] Blokada edycji dla transakcji na usuniętym koncie
  - [x] Edycja `booked_at` niezależnie od `date` (nie wpływa na saldo, tylko na okres) **[plan §1]**
  - [ ] Edycja transakcji typu `transfer` (z ustawionym `transfer_id`) — kwota tylko po „Rozłącz transfer" **[plan §4]**
- [~] Usuwanie transakcji:
  - [x] Aktualizacja salda deltą (odwrócenie wpływu)
  - [x] Blokada usuwania dla transakcji na usuniętym koncie
  - [x] UI: akcja usuwania (Index + Edit, dialog potwierdzenia)

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
- [~] Modal krok 3: mapowanie kolumn (zgodnie z PRD — **bez edycji w UI**; mapowanie z adaptera):
  - [x] Wymagane: data, kwota, opis — `BankImportAdapter::defaultMapping()` + auto-mapowanie nagłówków
  - [x] Opcjonalne: `subject` (per bank, np. BNP: `subject_positive` / `subject_negative`)
  - [x] Mapowanie po nazwach nagłówków (headers zawsze obecne)
  - [ ] Zapisywanie mapowania per user + bank (`ImportMapping` + fingerprint) — sekcja 7
- [ ] Auto-commit importu (bez preview):
  - [x] Walidacja + dedupe + zapis w jednej akcji
  - [x] Blokada ponownego commitu tego samego importu (gdy status != `draft`)
  - [x] Podsumowanie końcowe: `rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`
- [~] Widok wyniku:
  - [x] Po commit redirect do strony transakcji (`TransactionsIndexQuery`)
  - [x] Lista błędnych wierszy w modalu importu + banner na index (`import_failed_rows`, dismiss ręczny) **[branch 2026-06]**

#### 6.2 Parsowanie i walidacja
- [x] CSV:
  - [x] Autodetekcja separatora
  - [x] Kwoty — `App\Support\Imports\AmountParser` (separator tysięcy, nawiasy, waluty) **[plan §5.2]**
  - [x] Kodowanie — `App\Support\Imports\FileEncodingNormalizer` (UTF-8 / Windows-1250 / ISO-8859-2, BOM) **[plan §5.1]**
- [x] XLSX:
  - [x] Importować pierwszy arkusz
- [x] Data:
  - [x] Parsowanie do formatu daty (prezentacja DD-MM-YYYY; storage jako date)
  - [x] `App\Support\Imports\DateParser` — wiele formatów + odcinanie suffixu czasu **[plan §5.3]**
- [x] Kwota:
  - [x] Ujemne kwoty → wydatek, dodatnie → przychód (ustawić `type`)
  - [x] **Kwota 0 → błąd** — `AmountParser` / `TransactionType::fromAmount` → `ImportFailedRow` lub `rows_failed_validation++` **[plan §6]**
- [~] `subject`:
  - [~] Ekstrakcja per bank (adaptery) z `description` lub innych pól, jeśli bank tego wymaga (na MVP: `subject` z kolumny mapowania, jeśli istnieje)
  - [ ] **Usunąć mapowanie `Kategoria → subject` w `MBankImportAdapter`** (nadal w `defaultMapping`) **[plan §12.1]**
  - [x] BNP Paribas: `subject_positive` / `subject_negative` + `SubjectSanitizer` **[branch 2026-06]**
  - [x] Fallback: pozostawić puste, jeśli nie da się wyciągnąć **[Assumption]**

#### 6.3 Deduplikacja (zawsze pomijamy w imporcie)
- [x] Klucz dedupe importu: `date + amount + normalized_description` w obrębie `account_id`. **Bez `bank_reference_id`** — wspierane banki (BNP, mBank) nie eksportują takiego identyfikatora.
- [x] Zaimplementować normalizację opisu:
  - [x] `trim`
  - [x] `case-fold` (np. lowercase)
  - [x] standaryzacja whitespace (wielokrotne spacje → jedna)
- [x] Import używa tej samej logiki dedupe niezależnie od banku (adapter może dostarczyć wstępnie oczyszczony opis).
- [ ] Telemetria: `import_rows_skipped_duplicate`, `transaction_manual_duplicate_confirmed` **[plan §2]**.

#### 6.4 Salda po imporcie + chunked processing
- [x] Agregować sumę kwot tylko dla faktycznie utworzonych transakcji (`imported_amount_sum`).
- [x] Wykonać jedną aktualizację `current_balance` po przetworzeniu importu.
- [x] Zabezpieczyć przed podwójnym zapisem (idempotencja importu przez `import_id` + dedupe).
- [x] **Chunked processing**: batch po `config('imports.chunk_size', 500)`, `flushChunk()` w `DB::transaction`, bulk insert; `lockForUpdate` konta tylko przy finalnym zapisie salda **[plan §7]**.
- [ ] Importer odporny na `Lock wait timeout` przy pliku 5000+ wierszy (test obciążeniowy).

#### 6.5a Realtime status importu (MVP)
- [x] Włączyć aktualizację statusu importu przez Reverb (`queued` → `processing` → `committed|failed`).
- [~] **Broadcast progresu** — `ImportStatusUpdated` po chunk (throttle `imports.progress_broadcast_interval_seconds`) **[plan §8]**.
- [ ] **Polling jako fallback w UI** (co 5s, gdy WebSocket rozłączony) — `useImportStatus` composable **[plan §8]**.
- [x] Event `import.updated` z payloadem statusu, liczników `rows_*` i opcjonalnie `failed_rows` po commit.
- [~] Payload `progress` — liczniki w evencie; brak osobnego klucza `progress` **[plan §8]**.

#### 6.5 Enrichment `subject`/`description` z „pamięci" (Typesense)
- [x] Dodać „surowy opis z wyciągu" do transakcji/importu (np. `raw_statement_description`) i przechowywać go dla transakcji z importu.
- [x] Typesense collection „pamięci" (per user + bank):
  - [x] Klucze normalizacyjne (strict/relaxed) dla `raw_statement_description`
  - [x] Przechowywane wartości: `learned_subject`, `learned_description`, `updated_at`
- [x] Import: dla każdej transakcji spróbować dopasować pamięć po `raw_statement_description` i auto-uzupełnić `subject`/`description` (best-effort; brak trafienia lub brak Typesense → fallback).
- [x] Edycja transakcji: jeżeli transakcja pochodzi z importu i user zmieni `subject` i/lub `description`, wykonać upsert do pamięci w Typesense.
- [x] Izolacja danych: pamięć musi być ściśle per user (bez możliwości dopasowań między użytkownikami).

#### 6.6 Identyfikacja transferów podczas importu (matcher) [plan §4]
- [x] Klasa `App\Imports\TransferMatcher` z metodą `matchAfterImport(Import $import): TransferMatcherResult`.
- [x] Heurystyki dopasowania (kolejność):
  - [x] **probable** (auto-link): inne `account_id`, te same `user_id` i waluta, przeciwne znaki kwot, identyczne `|amount|`, `|date_a − date_b| ≤ 3 dni`, opisy zawierają któryś z tokenów konfiguracyjnych (`przelew własny`, `przelew wewn`, `transfer`, `własny`, `between accounts`).
  - [x] **manual** (do potwierdzenia): jak wyżej, ale bez tokena „transfer" w opisie.
  - [x] **ambiguous → manual**: gdy >1 kandydatka — brak auto-link; manual do najlepszej pary + telemetria `transfer_match_skipped_ambiguous`.
- [x] Lista tokenów w `config/imports.php` (`transfer_tokens`).
- [x] Auto-link: ustawia wspólny `transfer_id = (string) Str::uuid()`, oba `transfer_match_status = 'auto'`, oba `type = 'transfer'`. **Bez ponownej aktualizacji salda** (kwoty już zaksięgowane).
- [x] Manual link: ustawia obu transakcjom wzajemny `transfer_candidate_for_id` + `transfer_match_status = 'manual'`.
- [x] Wywołanie `TransferMatcher::matchAfterImport` w `CommitImport::handle` po pętli wierszy, przed broadcastem `Committed`.
- [x] UI: baner `TransferCandidatesBanner` na `transactions/Index` (prop `pending_transfer_candidates`) — bez osobnej strony / menu.
- [x] Akcje:
  - [x] „Potwierdź transfer" (`POST /transfers/candidates/{id}/confirm`) — ustawia wspólny `transfer_id`, `type=transfer`, `transfer_match_status='manual'`.
  - [x] „To nie transfer" (`POST /transfers/candidates/{id}/reject`) — `transfer_match_status='rejected'` na obu, czyści `transfer_candidate_for_id`.
- [x] Akcja „Rozłącz transfer" (`POST /transfers/{transferId}/unlink`) — czyści `transfer_id` na obu nogach, przywraca `type` na podstawie znaku `amount`, status `rejected` (backend; przycisk w Edit — poza tym PR).
- [x] Telemetria: `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`.
- [x] Walidacja waluty: różne waluty → brak dopasowania (na MVP).

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
- [ ] Transakcje: `transaction_create_opened`, `transaction_created`, `transaction_updated`, `transaction_deleted`, `transaction_manual_duplicate_confirmed`
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
- [~] Autoryzacja i izolacja danych — częściowo (`TransactionAuthorizationTest`; pełny pakiet: sekcja 16).
- [x] CRUD kont (`tests/Feature/Accounts/*`).
- [x] CRUD transakcji — saldo deltą (`TransactionStoreTest`, `TransactionUpdateTest`, `TransactionDeleteTest`).
- [x] Transfer (`tests/Feature/Transfers/*`).
- [~] Import:
  - [x] walidacja, dedupe, liczniki, blokada ponownego commitu (`tests/Feature/Imports/*`)
  - [x] `ImportFailedRow` + dismiss (`CommitImportFailedRowsTest`, `ImportFailedRowDismissTest`)
  - [ ] retry joba: 3 próby tylko dla błędów technicznych
  - [ ] partial import przy błędzie krytycznym w jobie

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
- [x] `RateLimiter::for('imports', ...)` w `AppServiceProvider::boot` — 10/min per `user_id` (fallback per IP).
- [ ] `RateLimiter::for('api', ...)` — 60/min per zalogowanego użytkownika.
- [x] Middleware `throttle:imports` na trasach upload/commit w `routes/imports.php`.

#### 12.2 Konto Cash bez 500
- [x] `PrepareImportUpload` → `PrepareImportUploadResult` z kodem `bank_unsupported`.
- [x] `ImportController::upload` — 422 z `message_key = imports.errors.bank_unsupported`.
- [x] UI: toast w `ImportDialog.vue` dla `bank_unsupported`.

#### 12.3 Mass assignment
- [~] Modele — `Transaction` ma `$fillable`; pozostałe (`Account`, `Import`, `AccountBalanceAdjustment`) nadal `$guarded = []`.
- [ ] `Model::shouldBeStrict()` w `AppServiceProvider::boot()` (poza prod).

---

### 13) Telemetria **[plan §10]**

- [ ] Klasa `App\Telemetry\Event::record(string $name, array $payload, ?int $userId = null)`.
- [ ] Kanał loga `telemetry` w `config/logging.php` (daily, JSON line).
- [ ] Wywołania w warstwie domeny / akcji:
  - [ ] Auth: `user_registered`, `user_logged_in`, `user_login_failed`
  - [ ] Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`
  - [ ] Transakcje: `transaction_created`, `transaction_updated`, `transaction_deleted`, `transaction_manual_duplicate_confirmed`
  - [ ] Lista (front-end POST `/telemetry/event`, throttle 60/min): `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`, `transaction_create_opened`
  - [~] Transfer: `transfer_created`, `transfer_failed_validation`, `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous` (logi `telemetry`; eventy listenerów częściowo), `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`
  - [ ] Import: `import_started`, `import_completed`, `import_failed`, `import_mapping_saved`, `import_mapping_reused`, `import_type_inferred`, `import_bank_resolved_from_account`, `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss`
- [ ] Helper front-end `resources/js/lib/telemetry.ts` z `track(name, payload)`.

---

### 14) Komendy artisan i scheduler **[plan §3, §12.4]**

- [x] `php artisan accounts:recalculate-balance {account?} [--all] [--dry-run]`
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

- [ ] `ImportController::index` — `paginate(20)` zamiast `latest()->limit(30)` (jeśli endpoint listy importów jest w scope).
- [ ] Sortowanie listy transakcji po `amount` z tie-breakerem `booked_at desc, id desc` (po wdrożeniu `booked_at`).
- [ ] Usunięcie `Kategoria → subject` z `MBankImportAdapter::defaultMapping`.
- [ ] Wymagany wpis audytowy w `account_deletions` przy `AccountController::destroy`.


## Checklist implementacyjna (MVP) — Wallet Master

Cel: zrealizować zakres z `.docs/prd.md` (terminologia: **Konto** / **Transakcja** / **Import** / **Transfer**).

> **Uwaga.** Zadania poza podstawowym zakresem PRD są oznaczone tagiem `[plan]` przy nazwie sekcji lub punktu (sekcje 12–17).

> **Ostatnia synchronizacja:** 2026-06-03 (branch `feature/budget-goals-ux` — cele + UX budżetu; 287 testów PASS). Poprzednio: `feat/categories` (wave 2 kategorie/budżet). Scalone: `improvement/telemetry` → `improvement/a11y-mvp` → `improvement/release-nfr-tests` → `develop`. Architektura Variant A — **zakończona** (reguła `.cursor/rules/wallet-architecture.mdc`). PRD kanoniczny: `.docs/prd.md`. Specyfikacje i plany Superpowers: `.docs/superpowers/`.
>
> **Audyt kodu (2026-06-03):** MVP Must (FR-A1, FR-K1/K2, FR-T1/T2/T3, FR-S1, FR-I1–I4) — wdrożone w kodzie; Should (FR-A2, FR-I5, FR-I6) — wdrożone z drobnymi lukami UI (patrz §4). Telemetria (§8/§13), A11y/UX (§9), NFR (§12), manual QA (§10.2) i pre-flight (§0) — zweryfikowane na `develop`. Testy: 250 passed. Otwarte (poza MVP release): PHPStan baseline (19 istniejących), edycja kwoty transferu bez unlink, test obciążeniowy importu. Wycofane z MVP: duplicate-check UI, `account_deletions`, `ImportMapping`, telemetria `import_mapping_*`.

---

### 0) Uruchomienie projektu / baseline
- [x] Uruchom aplikację lokalnie: `./vendor/bin/sail up -d`, `./vendor/bin/sail npm run dev` — smoke: login → `/accounts` → `/transactions` (Inertia bez błędów w konsoli).
- [x] Konwencja nazw w UI: „Transakcja” / „Konto” (zgodne z PRD i `pl.json`).
- [x] Formaty prezentacji w UI:
  - [x] Data **DD-MM-YYYY** (DatePicker, lista transakcji)
  - [x] Kwoty PL (locale `pl`, separatory w Resource/Vue)

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
  - [x] Unique `(account_id, dedupe_hash)` zostaje dla importu; ręczne wpisy używają `manualDedupeHash()` (UUID) — migracja non-unique **nie jest potrzebna** **[plan §2, audyt 2026-06-03]**

---

### 2) Autoryzacja i izolacja danych
- [x] Zaimplementować autoryzację per zasób (konto/transakcja/import) — Policies (`Account`, `Transaction`, `Import`, `ImportFailedRow`).
- [x] Dodać testy izolacji danych (min. 2 użytkowników, próby odczytu/edycji cudzych zasobów):
  - [x] `tests/Feature/Transactions/TransactionAuthorizationTest.php` (create/edit/store/update/destroy między userami)
  - [x] `tests/Feature/Authorization/AccountIsolationTest.php`
  - [x] `tests/Feature/Authorization/TransactionIsolationTest.php`
  - [x] `tests/Feature/Authorization/ImportIsolationTest.php`
  - [x] `tests/Feature/Imports/TypesenseMemoryIsolationTest.php`
- [x] Upewnić się, że reset hasła nie ujawnia czy email istnieje (`PasswordResetLinkController` — stały komunikat statusu; FR-A2).

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
  - [x] Zablokować edycję/usuwanie transakcji na usuniętym koncie:
    - [x] backend (policy)
    - [x] UI (blokady/ukrycie akcji)
  - [x] Zablokować import i transfer dla usuniętego konta:
    - [x] backend (policies + middleware konta aktywnego)
    - [x] UI (blokady/CTA/disabled na liście transakcji i formularzach)
- [x] Korekta salda:
  - [x] Akcja „Ustaw saldo" (manual adjustment).
  - [x] Audit trail minimalny (kto/kiedy/stara→nowa wartość) w `account_balance_adjustments`.
  - [x] **Zmiana implementacji:** korekta tworzy transakcję typu `adjustment` z `amount = newBalance - currentBalance`; saldo aktualizowane tak samo jak dotychczas (zapis `current_balance` po delcie) **[plan §3]**.
  - [x] Komenda `php artisan accounts:recalculate-balance {account?} [--all] [--dry-run]` **[plan §3]**.

---

### 4) Transakcje — API/CRUD + UI
- [x] Lista transakcji:
  - [x] Filtry: konto, zakres dat
  - [x] **Filtry dat po `COALESCE(booked_at, date)`** (wyświetlana data okresu) **[plan §1]**
  - [x] **Persistencja filtrów/sortu/strony** w sesji + redirect (`TransactionsIndexQuery`) **[branch 2026-06]**
  - [x] Sort: data/kwota
  - [x] Sort `date` → `COALESCE(booked_at, date)` + tie-breaker `date desc, id desc` **[plan §1, §12.5]**
  - [x] Paginacja (backend)
  - [x] Podsumowanie zakresu: suma wpływów i wydatków (oddzielnie)
  - [x] **Wykluczenie wewnętrznych transferów** z `summary` (`transfer_id IS NULL`) **[plan §12.2]**
  - [x] Empty state + CTA
  - [x] Jedna kolumna daty okresu (`booked_at ?? date`) + tooltip surowego opisu wyciągu importu **[plan §1, PRD FR-T2, branch 2026-06]**
  - [x] Badge „Korekta" dla transakcji typu `adjustment` **[plan §3]**
- [x] Dodanie transakcji:
  - [x] Pola: data (DD-MM-YYYY), kwota (decimal), opis, subject (opcjonalny)
  - [x] Pole `booked_at` (DD-MM-YYYY) z domyślną wartością równą `date` (Create/Edit) **[plan §1]**
  - [x] Ustalenie typu na podstawie znaku kwoty (ujemna=wydatek, dodatnia=przychód)
  - [x] Egzekwowanie `amount != 0` w warstwie domeny przez enum `TransactionType::fromAmount` (Store/Update manual + wiersze importu) **[plan §3, §6]**
  - [x] Walidacje: kwota != 0 (FormRequest); konto nieusunięte
  - [x] Aktualizacja `current_balance` konta deltą kwoty
  - [x] Brak blokady duplikatów w `StoreTransactionRequest` / `UpdateTransactionRequest` — ręczne duplikaty przez `manualDedupeHash()` **[plan §2]**
- [~] Edycja transakcji:
  - [x] Zmiana pól i przeliczenie delty salda (stara kwota → nowa kwota)
  - [x] Blokada edycji dla transakcji na usuniętym koncie
  - [x] Edycja `booked_at` niezależnie od `date` (nie wpływa na saldo, tylko na okres) **[plan §1]**
  - [x] Edycja transakcji typu `transfer` (z ustawionym `transfer_id`) — kwota/konto zablokowane; unlink w Edit **[plan §4, 2026-06-03]**
- [x] Usuwanie transakcji:
  - [x] Aktualizacja salda deltą (odwrócenie wpływu)
  - [x] Blokada usuwania dla transakcji na usuniętym koncie
  - [x] UI: akcja usuwania (Index + Edit, dialog potwierdzenia)

---

### 5) Transfer — jedna akcja → 2 transakcje
- [~] UI “Transfer”:
  - [x] Konto źródłowe != konto docelowe
  - [x] Użytkownik wpisuje kwotę dodatnią, system zapisuje `-X` i `+X` (walidacja wejścia)
  - [x] Data wspólna
  - [x] Opis (wspólny) + subject (opcjonalny)
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
  - [x] Mapowanie wyłącznie z adaptera banku — **bez** zapisu profili ani UI mapowania (PRD FR-I1/FR-I4; plan §11 wycofany z MVP)
- [x] Auto-commit importu (bez preview):
  - [x] Walidacja + dedupe + zapis w jednej akcji
  - [x] Blokada ponownego commitu tego samego importu (gdy status != `draft`)
  - [x] Podsumowanie końcowe: `rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`
- [x] Widok wyniku:
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
- [x] `subject`:
  - [x] Ekstrakcja per bank (adaptery) — `subject` z kolumny mapowania / BNP positive/negative; bez ekstrakcji z `description` (MVP)
  - [x] **mBank: bez mapowania `Kategoria → subject`** w `MBankImportAdapter::defaultMapping` **[plan §12.1]**
  - [x] BNP Paribas: `subject_positive` / `subject_negative` + `SubjectSanitizer` **[branch 2026-06]**
  - [x] Fallback: pozostawić puste, jeśli nie da się wyciągnąć **[Assumption]**

#### 6.3 Deduplikacja (zawsze pomijamy w imporcie)
- [x] Klucz dedupe importu: `date + amount + normalized_description` w obrębie `account_id`. **Bez `bank_reference_id`** — wspierane banki (BNP, mBank) nie eksportują takiego identyfikatora.
- [x] Zaimplementować normalizację opisu:
  - [x] `trim`
  - [x] `case-fold` (np. lowercase)
  - [x] standaryzacja whitespace (wielokrotne spacje → jedna)
- [x] Import używa tej samej logiki dedupe niezależnie od banku (adapter może dostarczyć wstępnie oczyszczony opis).

#### 6.4 Salda po imporcie + chunked processing
- [x] Agregować sumę kwot tylko dla faktycznie utworzonych transakcji (`imported_amount_sum`).
- [x] Wykonać jedną aktualizację `current_balance` po przetworzeniu importu.
- [x] Zabezpieczyć przed podwójnym zapisem (idempotencja importu przez `import_id` + dedupe).
- [x] **Chunked processing**: batch po `config('imports.chunk_size', 500)`, `flushChunk()` w `DB::transaction`, bulk insert; `lockForUpdate` konta tylko przy finalnym zapisie salda **[plan §7]**.
- [ ] Importer odporny na `Lock wait timeout` przy pliku 5000+ wierszy (test obciążeniowy).

#### 6.5a Realtime status importu (MVP)
- [x] Włączyć aktualizację statusu importu przez Reverb (`queued` → `processing` → `committed|failed`).
- [x] **Broadcast progresu** — `ImportStatusUpdated` po chunk (throttle `imports.progress_broadcast_interval_seconds`) **[plan §8]**.
- [x] **Polling co 5s** w `ImportDialog.vue` podczas kroku `processing` (równolegle z Echo; bez osobnego composable) **[plan §8]**.
- [x] Event `import.updated` z payloadem statusu, liczników `rows_*` i opcjonalnie `failed_rows` po commit.
- [x] Payload `progress` w `ImportStatusUpdated` **[plan §8]**.

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
- [x] Akcja „Rozłącz transfer" (`POST /transfers/{transferId}/unlink`) — czyści `transfer_id` na obu nogach, przywraca `type` na podstawie znaku `amount`, status `rejected`; przycisk + dialog na `transactions/Edit`.
- [x] Telemetria: `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`.
- [x] Walidacja waluty: różne waluty → brak dopasowania (na MVP).

---

### 7) Adaptery banków + profile mapowań
- [x] Zdefiniować listę banków (enum/slug): `BnpParibas`, `MBank`, `Cash` (Gotówka).
- [x] Ikony banków:
  - [x] Assets: `Bank::bankIconUrl()` + `public/icons/banks/{bnp-paribas,mbank}.jpeg`; testy; Cash — ikona `Coins` w UI
  - [x] Mapowanie ikon po slugu `bank` w UI (`AccountResource::bank_icon_url`)
- [x] Zdefiniować interfejs adaptera importu (`BankImportAdapter`):
  - [x] identyfikator banku (`bank(): Bank` — zamiast legacy `bank_key`)
  - [x] ekstrakcja `subject` (na MVP: kolumna mapowania / BNP `subject_positive`/`subject_negative`; bez reguł z `description`)
  - [x] pre-processing `description` — nie wymagane w MVP (YAGNI; FR-I4)
- [~] Mechanizm rejestracji adapterów + fallback “Generic”. (jest resolver, fallback = wyjątek)
- [x] Resolver adaptera po `Account.bank` (bank nie wybierany osobno w imporcie).
- [x] Implementacje adapterów (MVP):
  - [x] `BnpParibas`
  - [x] `MBank`
  - [x] `Cash` (konto gotówkowe; import niewspierany lub ograniczony — decyzja produktowa/techniczna)
- [x] ~~Model `ImportMapping`~~ — **wycofane z MVP** (sprzeczne z PRD: brak ręcznego mapowania w UI; brak migracji `import_mappings` w repo) **[plan §11, audyt 2026-06-03]**

---

### 8) Telemetria / eventy (min.)
- [x] Auth: `user_registered`, `user_logged_in`, `user_login_failed`
- [x] Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`
- [x] Transakcje: `transaction_create_opened`, `transaction_created`, `transaction_updated`, `transaction_deleted`
- [x] Lista: `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`
- [x] Transfer: `transfer_created`, `transfer_failed_validation`
- [x] Import: `import_row_validation_failed`, `import_started`, `import_completed`, `import_failed` — `Event::record` w `CommitImport` / job / listenerach; realtime: `ImportStatusUpdated` / Echo

---

### 9) UX jakościowe (do odhaczenia)
- [x] Wszystkie formularze mają:
  - [x] walidację inline
  - [x] czytelne komunikaty błędów po polsku
  - [x] stany loading/disabled podczas requestów
- [x] Empty states (konta/transakcje/import) mają jasne CTA.
- [x] A11y:
  - [x] pełna obsługa klawiaturą
  - [x] focus states widoczne
  - [x] poprawne `label`/aria
  - [x] kontrast WCAG AA

---

### 10) Testy (Pest) + QA checklist

#### 10.1 Testy automatyczne (minimum)
- [x] Autoryzacja i izolacja danych — `TransactionAuthorizationTest` + `tests/Feature/Authorization/*`.
- [x] CRUD kont (`tests/Feature/Accounts/*`).
- [x] CRUD transakcji — saldo deltą (`TransactionStoreTest`, `TransactionUpdateTest`, `TransactionDeleteTest`).
- [x] Transfer (`tests/Feature/Transfers/*`).
- [~] Import:
  - [x] walidacja, dedupe, liczniki, blokada ponownego commitu (`tests/Feature/Imports/*`)
  - [x] `ImportFailedRow` + dismiss (`CommitImportFailedRowsTest`, `ImportFailedRowDismissTest`)
  - [x] import z samymi duplikatami → `rows_imported=0` (`CommitImportAllDuplicatesTest`)
  - [ ] retry joba: 3 próby tylko dla błędów technicznych
  - [ ] partial import przy błędzie krytycznym w jobie

#### 10.2 Manual QA (minimum)

Środowisko: `./vendor/bin/sail up -d`, `./vendor/bin/sail npm run dev`, URL z `.env` (`APP_URL`, zwykle `http://localhost`).

- [x] **Happy path:** rejestracja → nowe konto → transakcja ręczna → lista z filtrem dat/konta → import CSV (ten sam bank co konto) → transfer między kontami. Toasty sukcesu, brak błędów w konsoli przeglądarki.
- [x] **Import samych duplikatów:** zaimportuj plik, potem **ten sam** plik ponownie → toast/komunikat o duplikatach, `rows_imported=0` (w UI licznik importu; potwierdzone testem `CommitImportAllDuplicatesTest`).
- [x] **Usunięte konto:** usuń konto z transakcjami → transakcje nadal na liście (filtr „wszystkie konta” / konto w tabeli) → edycja/usunięcie transakcji zablokowane → import i transfer na to konto zablokowane (patrz też testy `Transaction*Test`, `ImportUploadTest`, `AccountActiveMiddlewareTest`).

---

### 11) Quality gates przed merge
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact` (uruchomić testy dotknięte zmianami)
- [ ] `vendor/bin/phpstan analyse` (Larastan) bez nowych ostrzeżeń — `./vendor/bin/sail php ./vendor/bin/phpstan analyse` (19 błędów w baseline repo, głównie sprzed NFR)
- [x] `npm run lint` + `npm run format` (jeśli dotyczy)
- [x] Brak logowania wrażliwych danych importu w produkcji (przegląd logów/handlerów) — `CommitImport` loguje `description_raw` max 80 znaków na `debug`; telemetria bez pełnych wierszy pliku

---

### 12) Bezpieczeństwo i UX poprawki **[plan §9]**

#### 12.1 Rate limiting
- [x] `RateLimiter::for('imports', ...)` w `AppServiceProvider::boot` — 10/min per `user_id` (fallback per IP).
- [x] `RateLimiter::for('api', ...)` — 60/min per zalogowanego użytkownika (`throttle:api` na `POST /telemetry/event`).
- [x] Middleware `throttle:imports` na trasach upload/commit w `routes/imports.php`.

#### 12.2 Konto Cash bez 500
- [x] `PrepareImportUpload` → `PrepareImportUploadResult` z kodem `bank_unsupported`.
- [x] `ImportController::upload` — 422 z `message_key = imports.errors.bank_unsupported`.
- [x] UI: toast w `ImportDialog.vue` dla `bank_unsupported`.

#### 12.3 Mass assignment
- [x] Modele domenowe — `$fillable` na `Transaction`, `Account`, `Import`, `ImportFailedRow`, `AccountBalanceAdjustment`; `User` / `Currency` bez zmian.
- [x] `Model::shouldBeStrict()` w `AppServiceProvider::boot()` (poza prod).

---

### 13) Telemetria **[plan §10]**

- [x] Klasa `App\Telemetry\Event::record(string $name, array $payload, ?int $userId = null)`.
- [x] Kanał loga `telemetry` w `config/logging.php` (daily, JSON line).
- [x] Wywołania w warstwie domeny / akcji:
  - [x] Auth: `user_registered`, `user_logged_in`, `user_login_failed`
  - [x] Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`
  - [x] Transakcje: `transaction_created`, `transaction_updated`, `transaction_deleted`
  - [x] Lista (front-end POST `/telemetry/event`, throttle 60/min): `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`, `transaction_create_opened`
  - [x] Transfer: `transfer_created`, `transfer_failed_validation`, `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`
  - [x] Import: `import_started`, `import_completed`, `import_failed`, `import_row_validation_failed`, `import_type_inferred`, `import_bank_resolved_from_account`, `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss`
  - [ ] Import (wycofane z MVP — brak UI mapowania): `import_mapping_saved`, `import_mapping_reused`
- [x] Helper front-end `resources/js/lib/telemetry.ts` z `track(name, payload)`.

---

### 14) Komendy artisan i scheduler **[plan §3, §12.4]**

- [x] `php artisan accounts:recalculate-balance {account?} [--all] [--dry-run]`
- [x] `php artisan imports:purge-old-files {--days=30}` — czyści pliki importów `Failed` starszych niż N dni.
- [x] Wpis w `routes/console.php` — `imports:purge-old-files` codziennie.

---

### 15) Retencja plików importu **[plan §12.4]**

- [x] `CommitImport::handle` na sukces: usuwa plik źródłowy (jak teraz).
- [x] Na `Failed`: `PreserveFailedImportSourceFile` → `storage/app/imports/{user}/{import}/source-failed.{ext}`.
- [x] Komenda `imports:purge-old-files` (sekcja 14) sprząta po 30 dniach (`PurgeOldImportFilesTest`).

---

### 16) Testy izolacji danych **[plan §13]**

- [x] `tests/Feature/Authorization/AccountIsolationTest.php`
- [x] `tests/Feature/Authorization/TransactionIsolationTest.php`
- [x] `tests/Feature/Authorization/ImportIsolationTest.php`
- [x] `tests/Feature/Imports/TypesenseMemoryIsolationTest.php`

---

### 17) Drobne, ale ważne **[plan §12]**

- [ ] `ImportController::index` — `paginate(20)` zamiast `latest()->limit(30)` (jeśli endpoint listy importów jest w scope).
- [x] Sortowanie listy transakcji po `amount` z tie-breakerem `COALESCE(booked_at, date) desc, id desc` **[plan §12.5]**.

---

### 18) Kategorie i budżet (wave 2, FR-C1–C8) **[plan kategorie/budżet]**

- [x] Migracje: `categories`, `category_*_estimates`, `transactions.category_id` + backfill
- [x] Zestaw startowy kategorii (`EnsureUserCategories`) przy rejestracji
- [x] CRUD kategorii (backend + `categories/Index.vue`)
- [x] Szacunki roczne i miesięczne (API + UI na kategoriach / budżecie miesięcznym)
- [x] `category_id` wymagane na transakcji, transferze i imporcie
- [x] Pamięć kategorii w DescriptionMemory + przypisanie przy imporcie
- [x] Widoki budżetu: `budget/Monthly.vue`, `budget/Yearly.vue`
- [x] Formularze transakcji/transferu — wybór kategorii
- [x] Filtr i kolumna kategorii na liście transakcji (FR-C8)
- [x] Nawigacja: „Budżet” w sidebarze
- [x] Testy feature: Categories, Budgets, rozszerzenia Transactions/Imports

---

### 19) Cele + UX budżetu (FR-G1–G5, FR-UX1) **[plan budget-goals-ux]**

- [x] Migracje: `goals`, `goal_*_estimates`, `transactions.goal_id`
- [x] CRUD celów (backend + `goals/Index.vue`)
- [x] Szacunki celów roczne/miesięczne (API + UI na celach / budżecie miesięcznym)
- [x] Cel wymagany na transferze z/do konta `Savings`; opcjonalny na wydatku
- [x] Budżet miesięczny: sekcja per-cel (`goal_rows`) zamiast agregatu `transfers_summary`
- [x] Plany P&L tylko na budżecie — `categories/Index.vue` bez pól szacunków
- [x] Budżet roczny: edycja szacunków rocznych P&L inline
- [x] Nawigacja: „Cele” w sidebarze
- [x] Migracja legacy: szacunek kategorii „Oszczędności” → cel „Oszczędności ogólne”
- [x] Testy feature: Goals, TransferGoal, TransactionGoal, MonthlyBudget (cele)


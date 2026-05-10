# Plan poprawek MVP — Wallet Master

Dokument operacyjny dla agenta AI, który ma wdrożyć zmiany. Każdy punkt zawiera: cel, pliki do dotknięcia, kroki, akceptację, testy. Zmiany w PRD/checkliście są równolegle wprowadzone w `.docs/prd.md` i `.docs/checklist.md`.

Obowiązuje stack: Laravel 13, PHP 8.5, Pest 4, Inertia v2 + Vue 3, Reverb, Typesense (Scout). Pint po każdej zmianie PHP. Każda funkcjonalna zmiana ma towarzyszący test (Pest, najczęściej feature).

Kolejność wykonania: 1 → 12. Punkty 1–6 to baza danych + core domeny, muszą być wykonane sekwencyjnie. Punkty 7–12 mogą być realizowane częściowo równolegle, ale po zamknięciu 1–6.

---

## 1. Kolumna `booked_at` na transakcjach

**Status.** Wykonane. Widok tabeli transakcji pozostaje przy obecnym UX: pokazuje datę operacji, a filtrowanie, sortowanie i podsumowanie działają po `booked_at`.

**Cel.** Oddzielić *datę operacji* od *daty przypisania do okresu rozliczeniowego*. Domyślnie `booked_at = date`, ale użytkownik może przesunąć transakcję w czasie (np. zwrot z karty 3.05 ujęty w okresie kwietnia).

**Wpływ.** Lista, filtry i podsumowanie domyślnie operują po `booked_at`. `date` zostaje datą faktyczną operacji.

### Kroki
1. Migracja `add_booked_at_to_transactions_table`:
   - kolumna `booked_at DATE NOT NULL` po `date`,
   - backfill: `UPDATE transactions SET booked_at = date`,
   - indeksy (zastąpić istniejące po `date`):
     - dropnąć: `transactions(account_id, date)`, `transactions(user_id, date)`,
     - dodać: `transactions(account_id, booked_at)`, `transactions(user_id, booked_at)`,
     - zachować osobny indeks `transactions(account_id, date)` jeśli używany przez dedupe (patrz pkt 2 — finalnie i tak zmienia się klucz dedupe).
2. Model `Transaction`: dopisać `booked_at` do `$casts` (`date`), dodać akcesory PHPDoc.
3. Akcje:
   - `StoreTransaction::handle` — `booked_at = $validated['booked_at'] ?? $date`.
   - `UpdateTransaction::handle` — analogicznie; przy braku w payloadzie nie nadpisuj.
   - `CreateTransfer::handle` — obie nogi transferu mają `booked_at = date`.
   - `CommitImport::handle` — `booked_at = parsedRow.date` (na MVP brak override w imporcie).
4. FormRequesty:
   - `StoreTransactionRequest`, `UpdateTransactionRequest`: dodać `'booked_at' => ['nullable', 'date_format:d-m-Y']`.
5. `TransactionController::index`:
   - filtry `from`/`to` działają po `booked_at` (nie po `date`),
   - sort `date` → wewnętrznie sortuj po `booked_at` (z tie-breakerem `date desc, id desc`),
   - dodać do wystawianego payloadu `booked_at` i `date_relative` także po `booked_at`,
   - `summary` (income/expense) liczone w oknie `booked_at`.
6. Vue:
   - `resources/js/pages/transactions/Index.vue` — bez zmiany układu tabeli; lista używa `booked_at` w filtrach, sortowaniu i `date_relative`, ale w tabeli nadal prezentuje datę operacji (`date`),
   - `resources/js/pages/transactions/Create.vue` + `Edit.vue` — pole „Data przypisania do okresu" z domyślną wartością równą `date`, walidacja inline.

### Akceptacja
- Domyślnie `booked_at = date` przy tworzeniu/imporcie.
- Edycja samego `booked_at` nie zmienia `date`.
- Lista i podsumowanie respektują `booked_at`.

### Testy (Pest, feature)
- `tests/Feature/Transactions/StoreTransactionTest.php` — domyślny `booked_at`, jawny `booked_at`.
- `tests/Feature/TransactionListBookedAtTest.php` — transakcja z `date=01-04`, `booked_at=30-03` pojawia się w filtrze marzec, nie kwiecień.
- `tests/Feature/Imports/CommitImportBookedAtTest.php` — import ustawia `booked_at = date`.

---

## 2. Deduplikacja v2 — `bank_reference_id` jako klucz główny

**Cel.** Wyeliminować false-positive na duplikatach (dwa zakupy w tym samym sklepie tego samego dnia za tę samą kwotę). Tam, gdzie bank dostarcza unikalny identyfikator transakcji w wyciągu, używamy go.

### Kroki
1. Migracja `add_bank_reference_id_to_transactions_table`:
   - kolumna `bank_reference_id VARCHAR(120) NULL`,
   - dropnąć dotychczasowy unique `(account_id, dedupe_hash)`,
   - dodać:
     - unique `(account_id, bank_reference_id)` z warunkiem `bank_reference_id IS NOT NULL` (MySQL: emulujemy przez generowaną kolumnę albo unikat partial przez aplikację — w MySQL bez partial używamy unique na `(account_id, bank_reference_id)` i akceptujemy NULL-y jako nie-kolizyjne z natury MySQL),
     - non-unique index `(account_id, dedupe_hash)` jako fallback.
2. `App\Imports\ParsedImportRow` — pole `?string $bankReferenceId`.
3. `AbstractBankImportAdapter::normalizeRow` — pole `bankReferenceId` rozszerzone (domyślnie `null`).
4. `MBankImportAdapter`:
   - parser kolumny `#Numer referencyjny` lub `#Identyfikator transakcji` (zależnie od formatu eksportu) → `bankReferenceId`.
5. `BnpParibasImportAdapter`:
   - parser kolumny `Numer referencyjny` / `Numer transakcji` → `bankReferenceId`.
6. `CommitImport::handle`:
   - logika dedupe:
     1. Jeżeli `bankReferenceId !== null` → `Transaction::where('account_id', $account->id)->where('bank_reference_id', $bankReferenceId)->exists()` → duplikat.
     2. W przeciwnym razie fallback do `dedupe_hash` (obecna logika).
7. `StoreTransactionRequest` i `UpdateTransactionRequest` — usunąć twardy walidator `dedupe_hash` na poziomie FormRequest. Dla ręcznych transakcji nie blokujemy, dopuszczamy duplikat na żądanie użytkownika.
8. Migracja danych: nie wymagana (nowa kolumna nullable).

### Akceptacja
- Import dwóch wierszy z tym samym `(date, amount, description)` ale różnymi `bankReferenceId` → obie transakcje w bazie.
- Import dwóch wierszy z tym samym `bankReferenceId` → druga pominięta jako duplikat.
- Ręczne dodanie transakcji o identycznych polach nie zwraca błędu walidacji.

### Testy
- `tests/Feature/Imports/CommitImportDedupeBankReferenceTest.php`:
  - `dataset` z parami: same ref → duplikat; różne ref → dwie różne; brak ref + identyczne pola → duplikat (fallback).
- `tests/Feature/Transactions/ManualDuplicateAllowedTest.php` — ręczne dodanie identycznej drugiej transakcji jest dozwolone.

---

## 3. Korekta salda jako transakcja typu `adjustment`

**Cel.** Zgodność z PRD FR-S1: korekta salda jest osobną transakcją typu `adjustment`, deltą wpływa na saldo, jest widoczna na liście transakcji i pozostawia ślad audytowy.

### Kroki
1. Migracja `change_type_column_on_transactions_table`:
   - rozszerzyć `type` do `VARCHAR(20)` (z 10),
   - kolumna `transactions.bank_reference_id` już istnieje po pkt 2.
2. Wprowadzić enum PHP `App\Enums\TransactionType`:
   - cases: `Income`, `Expense`, `Transfer`, `Adjustment`,
   - metoda `fromAmount(string $amountDecimal): self` — z odrzuceniem `0`.
3. Model `Transaction`: cast `'type' => TransactionType::class`.
4. `App\Actions\Accounts\AdjustAccountBalance` (nowa klasa, przerzucić logikę z `AccountBalanceController::update`):
   - w jednej `DB::transaction`:
     - lock `Account` (lockForUpdate),
     - oblicz `delta = newBalance - currentBalance`,
     - zapisz `Transaction` typu `Adjustment` z `amount = delta`, `description = 'Korekta salda'`, `subject = null`, `date = today`, `booked_at = today`,
     - zaktualizuj `current_balance = newBalance`,
     - zapisz wpis `account_balance_adjustments` (zachowujemy jako historię operacji audytowych).
5. `AccountBalanceController::update` — wywołuje akcję; usunąć dotychczasowy własny zapis.
6. Walidator `StoreTransactionRequest`/`UpdateTransactionRequest` — `Rule::notIn([0])` (już jest), ale w `CommitImport` i `StoreTransaction` egzekwujemy też w warstwie domeny (rzucamy `DomainException` przy `bccomp(amount,'0',2) === 0`).
7. Lista transakcji (`TransactionController::index`):
   - `summary.total_income`, `summary.total_expense` — bez zmian sumują po znaku kwoty (adjustment z `+` zwiększa income, z `-` zwiększa expense),
   - dodać do payloadu `type` (już jest) — UI pokazuje badge „Korekta" dla `adjustment`.
8. Komenda `php artisan accounts:recalculate-balance {account?}`:
   - przelicza `current_balance` jako `opening_balance + SUM(amount)` po wszystkich transakcjach (włącznie z `adjustment`),
   - flag `--dry-run` pokazuje różnicę bez zapisu,
   - flag `--all` przelicza wszystkie konta.

### Akceptacja
- „Ustaw saldo" tworzy transakcję `adjustment` z deltą i nie nadpisuje `current_balance` poza ramami delty.
- Komenda `accounts:recalculate-balance --dry-run` zwraca 0 różnic na świeżej bazie po dowolnej sekwencji operacji.

### Testy
- `tests/Feature/Accounts/AdjustAccountBalanceTest.php` — tworzy adjustment, sprawdza saldo, wpis w `account_balance_adjustments`, transakcję na liście.
- `tests/Unit/Console/RecalculateBalanceTest.php` — symuluje rozjazd, weryfikuje detection i naprawę.

---

## 4. Identyfikacja transferów podczas importu (FR-I6)

**Cel.** Po zaimportowaniu transakcji z banku A („Przelew na konto X -100 PLN"), gdy użytkownik importuje wyciąg z banku B („Przelew z konta Y +100 PLN"), system łączy te dwie transakcje wspólnym `transfer_id`.

**Założenia.**
- Pracujemy tylko w obrębie kont jednego użytkownika.
- Łączymy parę: jedna transakcja ujemna na koncie A + jedna dodatnia na koncie B.
- Match heurystyczny w 3 poziomach pewności: `definite`, `probable`, `manual`.

### Heurystyki dopasowania (sprawdzane w kolejności)
1. **Definite** — w obu transakcjach istnieje `bank_reference_id` postaci „PRZELEW WŁASNY/WEWNĘTRZNY" + ten sam IBAN (jeżeli kiedyś sparsujemy IBAN). Na MVP: pomiń.
2. **Probable** — wszystkie warunki:
   - inne `account_id` (te same `user_id`),
   - przeciwne znaki kwot, identyczna wartość bezwzględna `|amount|`,
   - `|date_a − date_b| ≤ 3 dni`,
   - oba opisy zawierają któryś z tokenów: `przelew własny`, `przelew wewn`, `transfer`, `własny`, `between accounts` (tokeny jako konfig w `config/imports.php`).
3. **Manual** — wszystko jak w 2 ale brak tokena „transfer" w opisie. Trafia do listy „Możliwe transfery" do potwierdzenia w UI.

### Kroki
1. Nowa kolumna `transactions.transfer_match_status` (`VARCHAR(20)`, default `'none'`): `none|auto|manual|rejected`.
2. Nowa kolumna `transactions.transfer_candidate_for_id` (`BIGINT NULL FK transactions.id`) — dla `manual` wskazuje proponowaną drugą nogę.
3. Klasa `App\Imports\TransferMatcher`:
   - metoda `matchAfterImport(Import $import): void`,
   - dla każdej nowo utworzonej transakcji w imporcie szuka przeciwstawnej kandydatki wśród transakcji innych kont użytkownika (filtr `transfer_id IS NULL`, status != `rejected`),
   - dla `probable` ustawia obu transakcjom wspólny `transfer_id = (string) Str::uuid()`, oba `transfer_match_status = 'auto'`, oba `type = 'transfer'`,
   - dla `manual` wpisuje `transfer_candidate_for_id` w obie strony (cross-link), bez `transfer_id`.
4. Wywołanie po commit importu w `CommitImport::handle` po zakończeniu pętli wierszy (ale **przed** finalnym `save()` importu — wewnątrz transakcji).
5. Saldo:
   - `auto` link nie zmienia salda (kwoty już są zaksięgowane jako income/expense — zmiana `type` nie modyfikuje `amount`),
   - przy `auto` linkowaniu nie wykonuj `bcadd` ponownie.
6. Endpoint `GET /transfers/candidates` + Vue strona „Możliwe transfery":
   - lista par `manual`,
   - akcje: „Potwierdź transfer" (ustawia wspólny `transfer_id`, `type=transfer`, status `manual`), „To nie transfer" (status obu = `rejected`, czyści `transfer_candidate_for_id`).
7. Edycja transakcji typu `transfer`:
   - jeśli ustawione `transfer_id` — zachowanie jak dla istniejącego transferu (edycja amount jest zabroniona, bo musiałaby być wykonana po obu stronach; alternatywnie pokazać komunikat „rozłącz transfer aby edytować").
   - akcja „Rozłącz transfer" (`POST /transfers/{transferId}/unlink`) — czyści `transfer_id` na obu nogach, ustawia `type` z powrotem na podstawie znaku amount, status `rejected`.
8. `DeleteTransaction::handle` już obsługuje `transfer_id` — sprawdzić, czy nadal działa po zmianach (nadal usuwa parę).

### Akceptacja
- Import wyciągu z mBanku (przelew własny -200) i potem BNP (przelew z mBank +200) → po imporcie 2 transakcje mają wspólny `transfer_id`, status `auto`, oba typu `transfer`.
- Import dwóch wyciągów z dziwnymi opisami bez tokena „transfer" → status `manual`, użytkownik ręcznie potwierdza.
- Po `auto` link saldo obu kont jest takie samo jak przed (bo zmiana to tylko metadata).

### Testy
- `tests/Feature/Imports/TransferMatcherAutoTest.php` — typowe przelewy własne (mBank ↔ BNP), z różnicami dat 0–3 dni.
- `tests/Feature/Imports/TransferMatcherManualTest.php` — opisy bez tokena → status `manual`, brak wspólnego `transfer_id`.
- `tests/Feature/Imports/TransferMatcherRejectsAmbiguousTest.php` — gdy istnieje >1 kandydatka, status `manual` (nie `auto`).
- `tests/Feature/Transactions/TransfersUnlinkTest.php` — endpoint unlink przywraca 2 osobne transakcje.

---

## 5. Encoding i parsowanie pliku — adaptery banków

### 5.1 Detekcja kodowania (cp1250/iso-8859-2/utf-8)

**Pliki.** `app/Imports/BankAdapters/AbstractBankImportAdapter.php`, `app/Imports/BankAdapters/MBankImportAdapter.php`.

#### Kroki
1. Wprowadzić helper `App\Support\Imports\FileEncodingNormalizer`:
   - `normalizeToUtf8(string $absolutePath): string` — wykrywa kodowanie (`mb_detect_encoding` z listą `['UTF-8','Windows-1250','ISO-8859-2']`, plus heurystyka „BOM"),
   - jeśli inne niż UTF-8 → tworzy plik tymczasowy `*.utf8` w `storage/app/imports/{user}/tmp/`, zwraca jego ścieżkę,
   - jeśli UTF-8 → zwraca oryginalną ścieżkę,
   - usuwa ewentualny BOM `\xEF\xBB\xBF`.
2. `AbstractBankImportAdapter::readCsv` i `MBankImportAdapter::parseMbankCsv` — wywołać normalizer przed otwarciem pliku.
3. `Storage::disk('local')` — `tmp/*.utf8` skasować po przetworzeniu (`finally`).

### 5.2 Parser kwot

#### Kroki
1. `App\Support\Imports\AmountParser::parse(string $raw): string` (numeric-string z 2 miejscami):
   - usuń waluty (`PLN`, `zł`, `EUR`, `USD`),
   - usuń spacje (włącznie z NBSP `\xC2\xA0`) i apostrofy,
   - jeżeli zawiera obie kropkę i przecinek → ostatni z nich = separator dziesiętny, drugi = separator tysięcy do usunięcia,
   - jeżeli tylko przecinek → przecinek = dziesiętny,
   - jeżeli tylko kropka → kropka = dziesiętny,
   - akceptuj nawiasy księgowe `(123,45)` jako wartości ujemne,
   - rzuć `RuntimeException` przy `0` lub niepoprawnym formacie.
2. Podmienić obecne `parseAmount` w `AbstractBankImportAdapter` i `MBankImportAdapter` na wywołanie helpera.
3. Zwracana wartość wciąż przechodzi przez `TransactionDedupe::amountToDecimalString`.

### 5.3 Parser dat

#### Kroki
1. `App\Support\Imports\DateParser::parse(string $raw): string` (`Y-m-d`):
   - wspierane: `d-m-Y`, `Y-m-d`, `d/m/Y`, `d.m.Y`, `Y.m.d`, `Y/m/d`,
   - po usunięciu suffixu czasu (np. `2024-04-01 12:34:56`) próba ponownego parse,
   - rzuć `RuntimeException` przy braku formatu.
2. Podmienić `parseDate` w `AbstractBankImportAdapter`.

### Akceptacja
- Test pliku w `cp1250` z polskimi znakami → opisy w bazie poprawne (UTF-8), `dedupe_hash` stabilny.
- Plik z kwotami `1.234,56` → 1234.56.
- Plik z datami `01.04.2026` → 2026-04-01.

### Testy
- `tests/Unit/Imports/AmountParserTest.php` — `dataset` ze wszystkimi wariantami.
- `tests/Unit/Imports/DateParserTest.php` — analogicznie.
- `tests/Feature/Imports/CommitImportEncodingTest.php` — fixture CSV w cp1250 (binarny), oczekiwane opisy w UTF-8.

---

## 6. Walidacja `amount != 0` + wartości na granicy

**Cel.** Spójna polityka odrzucania `0` w warstwie domeny i adaptera.

#### Kroki
1. `TransactionDedupe::amountToDecimalString` — pozostaje liberalne (po prostu format).
2. W `App\Enums\TransactionType::fromAmount` (z pkt 3) — jeśli `bccomp($amount,'0',2) === 0` → `throw new DomainException('Amount cannot be zero')`.
3. `StoreTransaction::handle` i `UpdateTransaction::handle` — używają enum `TransactionType::fromAmount`.
4. `CommitImport::handle` — wywołanie `TransactionType::fromAmount`; przy wyjątku zwiększ `rows_failed_validation` i kontynuuj pętlę.
5. `AmountParser` (pkt 5.2) — rzuca przy `0`.

### Testy
- `tests/Feature/Imports/CommitImportRejectsZeroAmountTest.php`.
- `tests/Feature/Transactions/StoreTransactionRejectsZeroAmountTest.php` (już istnieje walidator FormRequest, ale dodać warstwowy test).

---

## 7. Architektura `CommitImport` — chunked processing + krótkie transakcje DB

**Problem.** Obecna implementacja trzyma długą `DB::transaction` z odczytem pliku i pętlą insertów (`app/Imports/Workflow/CommitImport.php`).

#### Kroki
1. Refaktor `CommitImport::handle`:
   - jedna krótka `DB::transaction` na: pobranie locked `Import`, ustawienie `Processing`, broadcast (patrz pkt 8),
   - pętla wierszy w `chunk` (np. 500 wierszy) — każda chunka osobna `DB::transaction`:
     - dla wierszy chunka oblicz dedupe (z bank_reference_id i fallbackiem),
     - bulk insert via `Transaction::query()->insert($rows)` (po przetłumaczeniu na tablice z polami DB),
     - zaktualizuj `Import` liczniki (`rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`) inkrementalnie,
     - po chunk → broadcast aktualizacji statusu (status pozostaje `Processing`, ale liczniki rosną).
   - po pętli — krótka transakcja: aktualizacja `Account.current_balance` o sumę i ustawienie statusu `Committed`, broadcast.
2. Usuń `lockForUpdate()` na `Import` w pętli; zostaw lock tylko przy zmianie statusu.
3. Update salda: nie trzymaj `account` zlockowanego przez całą pętlę. Lockuj go tylko w finalnej transakcji.
4. `TransferMatcher::matchAfterImport` (pkt 4) wywołać po pętli ale przed broadcastem `Committed`.

### Akceptacja
- Plik 5000 wierszy importowany bez `Lock wait timeout`, równoległe ręczne dodawanie transakcji nie blokuje się dłużej niż 1s.
- W trakcie importu UI (pkt 8) widzi przyrost liczników.

### Testy
- `tests/Feature/Imports/CommitImportChunkedTest.php` — fixture 1500 wierszy, sprawdza że broadcast leci > 1 raz między start a end.
- `tests/Feature/Imports/CommitImportPartialFailureTest.php` — błąd techniczny w środku pętli → zachowane dotychczas zaimportowane chunki, status `Failed`, `error_summary` ustawione.

---

## 8. Reverb broadcast: status `Processing` i progress

**Cel.** UI widzi pełny cykl `queued → processing → committed`, plus przyrost liczników.

#### Kroki
1. `App\Events\ImportStatusUpdated` — pole payloadu `progress` (`{rows_total, rows_imported, rows_skipped_duplicate, rows_failed_validation}`).
2. `CommitImportJob::handle` — broadcast po przejściu na `Processing`.
3. `CommitImport::handle` — broadcast po każdym chunk (rate-limit do max 1/s w razie potrzeby).
4. Vue:
   - `resources/js/composables/useImportStatus.ts` — `useEcho` (kanał `private-imports.{importId}`) + fallback polling co 5s gdy WS rozłączony,
   - `resources/js/pages/transactions/import/Modal.vue` — pasek progresu + liczniki w czasie rzeczywistym.
5. `routes/channels.php` — autoryzacja `private-imports.{importId}` per `user_id`.

### Akceptacja
- Tail loga importu w UI pokazuje minimum 3 zdarzenia (queued → processing → committed) oraz przyrastające liczniki dla pliku >1000 wierszy.

### Testy
- `tests/Feature/Imports/ImportStatusEventsTest.php` — `Event::fake()` + assert wywołań kolejnych statusów + payload `progress`.

---

## 9. Bezpieczeństwo i UX

### 9.1 Rate limiting
1. `routes/web.php`:
   - grupa `Route::middleware(['throttle:imports'])` dla `imports/upload` i `imports/{import}/commit`,
   - `app/Providers/AppServiceProvider.php` — `RateLimiter::for('imports', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?? $r->ip()))`,
   - dodać `RateLimiter::for('api', ...)` jako globalny limiter 60/min na zalogowanego.
2. `routes/auth.php` — pozostaje `throttle:6,1` (już jest).

### 9.2 Cash bez 500
1. `PrepareImportUpload::execute` — zamiast `RuntimeException` zwraca `PrepareImportUploadResult::importsNotSupported(Bank::Cash)`.
2. `TransactionImportController::upload` — mapuje na `422` z message_key `imports.errors.bank_unsupported`.
3. UI — toast „Konta gotówkowe nie obsługują importu z pliku".

### 9.3 Mass assignment
1. `app/Models/Account.php` — zamiast `$guarded = []` wprowadzić `$fillable = ['user_id','currency_id','bank','type','name','opening_balance','current_balance']`.
2. Analogicznie `Transaction`, `Import`, `AccountBalanceAdjustment` — sprawdzić i ograniczyć `$fillable`.
3. Włączyć `Model::shouldBeStrict()` w `AppServiceProvider::boot` (poza prod jeśli ostro).

### Akceptacja
- 11. żądanie uploadu w 1 minucie zwraca 429.
- Próba uploadu na konto Cash → 422 z czytelnym komunikatem.

### Testy
- `tests/Feature/Imports/UploadRateLimitTest.php`.
- `tests/Feature/Imports/CashAccountUnsupportedTest.php`.

---

## 10. Telemetria — eventy mierzące success metrics

**Cel.** Sekcja 2 PRD wymaga mierzenia: import success rate, time-to-add, activation import 7d, data isolation.

#### Kroki
1. Klasa `App\Telemetry\Event` z metodą `record(string $name, array $payload, ?int $userId = null)`:
   - na MVP wpis do `Log::channel('telemetry')` (nowy kanał daily w `config/logging.php`),
   - JSON jednolinijkowy.
2. Eventy obowiązkowe (per checklist sekcja 8):
   - Auth: `user_registered`, `user_logged_in`, `user_login_failed`,
   - Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`,
   - Transakcje: `transaction_created`, `transaction_updated`, `transaction_deleted`,
   - Lista: front-end → POST `/telemetry/event` (rate-limited 60/min) — `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`, `transaction_create_opened`,
   - Transfer: `transfer_created`, `transfer_failed_validation`, `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`,
   - Import: `import_started`, `import_completed`, `import_failed`, `import_mapping_saved`, `import_mapping_reused`, `import_type_inferred`, `import_bank_resolved_from_account`, `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss`.
3. Front-end helper `resources/js/lib/telemetry.ts` z metodą `track(name, payload)`.

### Akceptacja
- Po 1 imporcie w logach `telemetry-YYYY-MM-DD.log` widoczne `import_started` i `import_completed` z licznikami.

### Testy
- `tests/Feature/Telemetry/TelemetryEventsTest.php` — `Log::shouldReceive` z assertami per event.

---

## 11. ImportProfile — zapis i automatyczne podpowiedzi mapowania

**Cel.** PRD FR-I4 — mapowanie zapamiętane per `user_id + bank` jest sugerowane przy kolejnym imporcie.

#### Kroki
1. W tabeli `import_mappings` (już istnieje) dodać unique `(user_id, bank, format_fingerprint)`.
2. Model `App\Models\ImportMapping` z relacjami i castem `mapping => array`.
3. `App\Imports\Workflow\PrepareImportUpload::execute`:
   - po `extractHeaders` zbuduj `formatFingerprint` (`sha1(implode('|', headers))`),
   - jeżeli istnieje `ImportMapping` o pasującym `(user_id, bank, format_fingerprint)` → użyj jego `mapping` jako default zamiast `defaultMapping($headers)`,
   - w przeciwnym razie fallback do `adapter->defaultMapping`.
4. `QueueImportCommit::execute`:
   - po sukcesie zaktualizuj/utwórz `ImportMapping` z bieżącym mapowaniem (`updateOrCreate`).
   - emit `import_mapping_saved` lub `import_mapping_reused`.
5. UI w modalu importu — krok 3 „Mapowanie kolumn":
   - dropdowny per pole (date/amount/description/subject) z listą `headers`,
   - checkbox „Zapisz to mapowanie dla tego banku",
   - jeżeli mapowanie istnieje → pre-selected, etykieta „Wczytano poprzednie mapowanie".

### Akceptacja
- Drugi import z tymi samymi headerami → mapowanie podpowiedziane bez interakcji.
- Zmiana headera w pliku → fingerprint inny → mapowanie nie sugerowane (fallback).

### Testy
- `tests/Feature/Imports/ImportMappingReuseTest.php`.

---

## 12. Drobne, ale wpływające na jakość

### 12.1 Subject z mBanku — nie używaj „Kategoria"
- `MBankImportAdapter::defaultMapping` — usuń `subject => 'Kategoria'`. Subject na MVP zostaje pusty dla mBanku, użytkownik uzupełnia ręcznie. Pamięć Typesense w pkt 5.5 PRD i tak nadrobi przy następnym imporcie po edycji.

### 12.2 Summary bez transferów
- `TransactionController::index` — `summary` query dodatkowo filtruje `whereNull('transfer_id')`, żeby wewnętrzne transfery nie zawyżały income/expense w okresie. PRD doprecyzowuje (zmiana w `.docs/prd.md` FR-T2).

### 12.3 Paginacja listy importów
- `TransactionImportController::index` — `paginate(20)` zamiast `latest()->limit(30)`.

### 12.4 Retencja pliku importu
- `CommitImport::handle` — po `Failed` **nie** kasuj pliku źródłowego, zapisz go do `storage/app/imports/{user}/{import}/source-failed.{ext}`.
- Dodać command `php artisan imports:purge-old-files {--days=30}` skasować pliki dla importów `Failed` starszych niż 30 dni; uruchamiać via scheduler.

### 12.5 Dedupe — wymagane wykluczenie soft-deleted accounts ze scope
- Indeks unique `(account_id, bank_reference_id)` nie wymaga zmian (account hard-delete = restrict; soft-delete nie usuwa wiersza).

### 12.6 Sortowanie listy transakcji — tie-breaker
- `TransactionController::index` — sort `amount` powinien mieć tie-breaker `booked_at desc, id desc` (po zmianach z pkt 1).

### 12.7 Audit trail usunięcia konta
- Tabela `account_deletions` (`id`, `account_id`, `user_id`, `transactions_count_at_delete`, `created_at`),
- Zapis przy `AccountController::destroy` przed `delete()`.

### Testy (po sekcjach)
- `tests/Feature/Transactions/SummaryExcludesTransfersTest.php`.
- `tests/Feature/Imports/FailedImportFileRetentionTest.php`.
- `tests/Feature/Accounts/AccountDeletionAuditTest.php`.

---

## 13. Plan testów izolacji danych (PRD sekcja 2 success metric)

#### Kroki
1. `tests/Feature/Authorization/AccountIsolationTest.php` — user A nie widzi/edytuje/usuwa konta usera B (4 metody × 4 scenariusze).
2. `tests/Feature/Authorization/TransactionIsolationTest.php` — analogicznie dla transakcji.
3. `tests/Feature/Authorization/ImportIsolationTest.php` — analogicznie dla importów (view, commit).
4. `tests/Feature/Imports/TypesenseMemoryIsolationTest.php` — pamięć Typesense user A nie wpływa na sugestie usera B (mock `DescriptionMemoryRepository`).

### Akceptacja
- `php artisan test --compact --filter=Isolation` zielone.

---

## 14. Kolejność wdrożenia (sprinty)

**Sprint 1 (fundament danych):** 1, 2, 3, 6.
**Sprint 2 (importer core):** 5, 7, 8.
**Sprint 3 (transfer detection):** 4.
**Sprint 4 (UX/produkcja):** 9, 10, 11, 12, 13.

---

## 15. Definition of Done (per zmiana)

- Zmiany kodu PHP przepuszczone przez `vendor/bin/pint --dirty --format agent`.
- Co najmniej 1 test Pest pokrywający zmianę zielony (`php artisan test --compact --filter=...`).
- Brak nowych ostrzeżeń z `vendor/bin/phpstan analyse` (Larastan).
- `npm run lint` + `npm run format` zielone (jeżeli zmiany w `resources/js`).
- Aktualizacja relevant fragmentów w `.docs/prd.md` i `.docs/checklist.md` jeżeli zmienia się zachowanie funkcjonalne.

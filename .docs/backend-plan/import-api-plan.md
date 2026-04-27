# Backend plan — 7.5 Import (CSV/XLSX)

## 1) FR summary
- Użytkownik wybiera konto, uploaduje plik CSV/XLSX, mapuje kolumny do pól `date`, `amount`, `description`, `subject`, widzi preview i dopiero potem uruchamia import (2-etapowo).
- Typ transakcji wynika ze znaku kwoty (ujemna=wydatek, dodatnia=przychód) i **kwota jest zapisywana z tym znakiem**.
- Deduplikacja jest **zawsze aktywna**: duplikaty na tym samym koncie po `date + amount + normalized_description` są pomijane, a licznik `rows_skipped_duplicate` rośnie.
- Mapowanie można zapisać i ponownie użyć “per bank” (bank wynika z `Account.bank`), a kod ma mieć miejsce na logikę bankowo-specyficzną (adaptery).
- Telemetry: `import_started`, `import_preview_generated`, `import_completed`, `import_failed`, `import_type_inferred`, `import_rows_skipped_duplicate` (agregat), `import_mapping_reused`, `import_mapping_saved`, `import_bank_resolved_from_account`.

## 2) Assumptions
- Import jest procesem **2-etapowym**: `preview` (walidacja + dedupe + podgląd) → `commit` (zapis w DB).
- CSV parsujemy bez dodatkowych zależności (wbudowane funkcje PHP). XLSX wymaga biblioteki (patrz “Open questions”).
- XLSX: importujemy **pierwszy arkusz**.
- Kwoty w nawiasach (format księgowy), np. `(123,45)` traktujemy jako ujemne.
- Normalizacja opisu dla dedupe: `trim` + `mb_strtolower` (case-fold) + standaryzacja whitespace (wielokrotne spacje/taby/newline → pojedyncza spacja).
- Zanonimizowane sample wyciągów do budowy/regresji adapterów trzymamy w `tests/Fixtures/import/` (w podkatalogach per bank lub z prefiksami nazw plików).

## 3) Implementation plan

### Data model
W repo istnieją już kluczowe elementy dla importu i dedupe:
- `imports` ma status, liczniki wierszy, `mapping` (JSON) i `error_summary`.
- `transactions` ma `normalized_description`, `dedupe_hash` oraz unikalność `unique(account_id, dedupe_hash)` (wymusza “always skip duplicates” nawet przy race condition).

Do dodania (minimalnie dla FR-I4 “mapowanie per bank”):
- **Nowa tabela** `import_mappings` (nazwa do dopasowania do konwencji repo):
  - `id`
  - `user_id` (FK, cascade)
  - `bank` (string, np. `BnpParibas`, `MBank`, `Cash`)
  - `mapping` (json) — snapshot mapowania (np. `{"date":"Data operacji","amount":"Kwota","description":"Opis","subject":"Nadawca/odbiorca"}`)
  - `format_fingerprint` (nullable string, max 255) — opcjonalnie do rozpoznawania wersji formatu (np. hash nagłówków); ułatwia aktualizacje mapowania po zmianie formatu banku.
  - timestamps
  - indeks/unikalność: `unique(user_id, bank)` (lub `unique(user_id, bank, format_fingerprint)` jeśli chcemy wspierać wiele formatów na bank).

Uwaga dot. “Cash”:
- Jeśli `Account.bank = Cash`, można pozwolić na import (użytkownik może mieć CSV “gotówka”), ale adapter może być “generic” bez dodatkowych reguł.

### Routes/API contracts
Preferowane są endpointy “resource-like” powiązane z kontem/importem. Proponuję minimum:

1) **Preview**
- `POST /accounts/{account}/imports/preview` (name: `accounts.imports.preview`)
- **Request (multipart/form-data)**:
  - `file` (required; csv/xlsx; max size np. 10–20MB)
  - `mapping` (required; JSON object)
    - `date`, `amount`, `description` (required)
    - `subject` (optional)
  - `save_mapping` (boolean, default false)
- **Response 200**:
  - `import` (draft): `{ id, status, rows_total, rows_failed_validation, rows_skipped_duplicate, mapping, bank }`
  - `preview_rows` (array; np. pierwsze 50–200 wierszy): każdy element:
    - `row_number`
    - `parsed`: `{ date, amount, description, subject, type }`
    - `issues`: lista błędów walidacji per wiersz (pusta jeśli OK)
    - `is_duplicate` (bool)
  - `summary`: `{ rows_total, rows_valid, rows_failed_validation, rows_skipped_duplicate }`
- **Błędy**:
  - `422` dla błędów walidacji requestu (brak mapowania, zły MIME/extension, zbyt duży plik).
  - `415` lub `422` dla nieobsługiwanego formatu/parsowania pliku (“Invalid file format” / “Unable to parse file”).

2) **Commit**
- `POST /imports/{import}/commit` (name: `imports.commit`)
- **Request (JSON)**:
  - brak dodatkowych pól (import jest przygotowany w `preview`) **albo** opcjonalne `confirm` boolean.
- **Response 200/201**:
  - `import`: `{ id, status="completed", rows_total, rows_imported, rows_failed_validation, rows_skipped_duplicate, committed_at }`
- **Błędy**:
  - `403` jeśli import nie należy do usera lub konto usunięte.
  - `409` jeśli `imports.status` nie jest `draft` (idempotencja/ochrona przed wielokrotnym commit).

3) **Get suggested mapping**
- `GET /accounts/{account}/imports/mapping` (name: `accounts.imports.mapping`)
- **Response 200**:
  - `bank`
  - `mapping` (nullable)
  - `source`: `none | saved_mapping`

Kontrakty “mapping”:
- Mapowanie po nazwach nagłówków (string → field) jest prostsze dla użytkownika, ale w CSV/XLSX czasem brak nagłówków. MVP: zakładamy nagłówki w pierwszym wierszu; jeśli ich brak, zwracamy czytelny błąd i prosimy o poprawny plik. (Jeśli repo/UI już wspiera mapowanie po indeksach kolumn, dopasować plan.)

### Domain/service layer
Proponowane komponenty (konkretne miejsca i nazwy do dopasowania do istniejących `app/Actions/*`):

- `App\Actions\Imports\GenerateImportPreview`
  - Input: `User $user`, `Account $account`, `UploadedFile $file`, `array $mapping`, `bool $saveMapping`
  - Output: `ImportPreviewResult` (array/DTO) zawierający draft `Import` oraz `preview_rows` i `summary`.
  - Odpowiedzialność:
    - Autoryzacja: konto należy do usera i nie jest soft-deleted.
    - Rozpoznanie banku: `bank = $account->bank` (emit telemetry `import_bank_resolved_from_account`).
    - Parsowanie pliku (CSV/XLSX) do “raw rows”.
    - Mapowanie raw row → canonical row: `{date, amount, description, subject}`.
    - Parsowanie dat i kwot z tolerancją formatów (walidacja + czytelny komunikat per wiersz).
    - Inferencja typu na podstawie znaku kwoty (emit `import_type_inferred` jako agregat lub per-row tylko wewnętrznie; telemetry preferowana agregacja).
    - Normalizacja opisu oraz wyliczenie `dedupe_hash` z (`date`, `amount`, `normalized_description`).
    - Dedupe:
      - Sprawdzenie istniejących transakcji po `account_id` i `dedupe_hash` (batch query po hashach z pliku).
      - Oznaczenie `is_duplicate` oraz policzenie `rows_skipped_duplicate` w preview.
    - Utworzenie/aktualizacja `imports` w statusie `draft`:
      - `mapping` snapshot
      - `rows_total`, `rows_failed_validation`, `rows_skipped_duplicate`
      - (opcjonalnie) `error_summary` w skróconej formie (bez surowych danych).
    - Zapisywanie mapowania per bank, jeśli `saveMapping=true`.

- `App\Actions\Imports\CommitImport`
  - Input: `User $user`, `Import $import`
  - Output: `Import` (completed) + liczniki
  - Odpowiedzialność:
    - Autoryzacja: import należy do usera; konto importu nie jest soft-deleted.
    - Wczytanie danych przygotowanych w preview.
    - Zapis transakcji w transakcji DB:
      - Dla każdego “valid non-duplicate row” wstawiamy `transactions`:
        - `amount` z zachowanym znakiem
        - `type`: determinujemy z `amount` (`expense` jeśli < 0, `income` jeśli > 0). (Dla `amount=0` → walidacja błędu.)
        - `normalized_description`, `dedupe_hash`, `import_id`
      - Dedupe enforce na poziomie DB: insert z obsługą konfliktu unikalności `unique(account_id, dedupe_hash)`:
        - MySQL: `insert ignore` lub `upsert` z “do nothing” semantyką.
        - Liczniki: jeśli konflikt → `rows_skipped_duplicate++`.
    - Aktualizacja salda konta zgodnie z FR-S1 (delta sumy zaimportowanych kwot) w tej samej transakcji DB.
    - Aktualizacja `imports`:
      - `status=completed`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`, `committed_at`
    - Zwrócenie rezultatu do kontrolera.

Gdzie trzymać dane pomiędzy preview i commit?
- Opcja rekomendowana (bez trzymania całego pliku w DB): w preview zapisujemy plik do `storage/app/imports/{user_id}/{import_id}/source.*` i w `imports.mapping` snapshot mapowania; commit ponownie parsuje plik i stosuje mapowanie (deterministyczne).
  - Plusy: brak dużych payloadów w DB, prostsze.
  - Minusy: commit jest “parse again”, ale akceptowalne w MVP.
- Alternatywa: zapis “canonical rows” w JSON (np. `imports.payload`) lub tabeli `import_rows` — odradzam w MVP bez potrzeby.

#### Parsowanie dat i kwot
Kwota:
- Akceptujemy wejście z `,` lub `.` jako separator dziesiętny.
- Usuwamy separatory tysięcy (spacje, `,`/`.` zależnie od heurystyki).
- Obsługujemy format nawiasowy: `(123,45)` → `-123.45`.
- Walidacja: `amount != 0`.

Data:
- Akceptujemy kilka formatów (np. `Y-m-d`, `d-m-Y`, `d.m.Y`, `d/m/Y`) i ISO.
- Jeśli nie da się sparsować: błąd per wiersz z informacją o wartości i oczekiwanych formatach.

#### Adaptery bankowe (“klasy banków”)
Wprowadzamy kontrakt:
- `BankImportAdapterInterface` z metodami:
  - `normalizeDescription(string $description): string`
  - `extractSubject(?string $subjectFromColumn, string $description): ?string`
  - `parseAmount(mixed $raw): Decimal` (albo `string` → decimal)
  - `parseDate(mixed $raw): CarbonImmutable`
  - (opcjonalnie) `supports(Bank $bank): bool`
- `BankImportAdapterResolver` wybiera adapter na podstawie `Account.bank` (telemetry `import_bank_resolved_from_account`).
- Default adapter `GenericImportAdapter` działa dla wszystkich, a bankowe (np. `MBankImportAdapter`) mogą nadpisywać ekstrakcję `subject` z opisu lub niestandardowe formaty kwot/dat.

### Authorization
Polityki/scoping:
- Konto i import zawsze scope’ujemy do zalogowanego usera (`whereBelongsTo($request->user())`).
- Blokady:
  - Import do soft-deleted konta: **403** (zgodnie z FR-K2 edge case).
  - Commit importu dla konta usuniętego pomiędzy preview a commit: **403**.

## 4) Test plan (Pest)
Testy feature (minimum, szybkie i krytyczne):

1) **Preview**
- `it('generates preview for valid csv and required mapping')`
  - Given user + active account
  - When upload CSV + mapping
  - Then 200, `import.status=draft`, `rows_total>0`, preview zawiera `type` i poprawnie sparsowane kwoty/daty
- `it('returns readable error for invalid file format')`
- `it('validates mapping requires date amount description')` (422)
- `it('blocks preview for soft-deleted account')` (403)

2) **FR-I2 sign & accounting parentheses**
- `it('infers expense for negative amount and persists negative amount on commit')`
- `it('treats parenthesis amount as negative')`

3) **FR-I3 dedupe always skip**
- Setup: istniejąca transakcja na koncie z tym samym `date + amount + normalized_description`
- `it('marks duplicate rows in preview and increments rows_skipped_duplicate')`
- `it('skips duplicates on commit and increments rows_skipped_duplicate')`
- `it('is race-safe thanks to unique(account_id, dedupe_hash)')` (opcjonalnie: symulacja 2 insertów; oczekiwany brak duplikatu)

4) **FR-I4 mapping per bank**
- `it('suggests saved mapping based on account bank')`
- `it('saves mapping per bank when save_mapping is true')`
- `it('emits mapping_reused telemetry when returning saved mapping')` (jeśli telemetria jest testowalna w repo przez `Event::fake()`).

Komendy:
- `./vendor/bin/sail artisan test --compact tests/Feature/Import*`
  - albo filter, jeśli testy są w jednym pliku: `--filter=Import`

## 5) Open questions
1) **XLSX parsing dependency**
- Rekomendacja: dodać `phpoffice/phpspreadsheet` (najprościej dla 1. arkusza).
- Alternatywa: `maatwebsite/excel` (większa “Laravel-owość”, ale cięższy wrapper).
- Bez dodatkowej zależności XLSX będzie trudny do zrobienia poprawnie (a to jest Must).

2) **Mapowanie po nagłówkach vs indeksach**
- Rekomendacja domyślna: mapowanie po nazwach nagłówków w 1. wierszu.
- Jeśli UI planuje mapowanie po indeksach (A/B/C), backend powinien przyjąć mapping w tej formie; logika adapterów się nie zmienia.


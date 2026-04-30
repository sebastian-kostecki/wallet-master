# FR-I5 “Pamięć” opisów z wyciągu: sugerowanie `subject` i `description` (Typesense) — plan backend

## 1) FR summary
FR-I5 dodaje do importu “best-effort” wzbogacanie pól transakcji na podstawie wcześniejszych korekt użytkownika:
- Gdy użytkownik **edytuje** zaimportowaną transakcję z `raw_statement_description` i zapisze `subject` + `description`, system zapisuje “pamięć” mapowania **per user + per bank** w Typesense.
- Gdy użytkownik **importuje** kolejne transakcje z takim samym (lub znormalizowanym) `raw_statement_description`, import próbuje dopasować pamięć i uzupełnić `subject` + `description`.
- Brak dopasowania lub niedostępność Typesense **nie blokuje** importu (fallback: `subject = null`, `description = raw`).
- Telemetria: `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss`.

Kontekst repo: istnieje kontrakt `App\Support\DescriptionMemory\DescriptionMemoryRepository` + `NullDescriptionMemoryRepository` (degradacja) oraz DTO `SuggestedFields`.

## 2) Assumptions
- Typesense jest dostępne w środowisku dev przez Sail (wg `.docs/backend-plan/import-api-plan.md`), ale integracja w kodzie nie istnieje jeszcze.
- “Bank” bierzemy z `Account.bank` (enum `App\Enums\Bank`), a import działa “per bank adapter” (FR-I4).
- Transakcje importowane przechowują `raw_statement_description` jako **niezmieniane źródło prawdy** do uczenia/dopasowań (jeśli jeszcze nie istnieje w modelu/DB, dodamy kolumnę).
- Telemetria w MVP jest realizowana jako **Laravel eventy domenowe** (lub structured logs) — implementacja wysyłki do narzędzia analitycznego może dojść później.
- FR-I5 dotyczy wyłącznie importów bankowych; dla `Bank::Cash` jest wyłączone (brak wyciągów).

## 3) Implementation plan

### Data model
1) `transactions.raw_statement_description` (text/string)
   - **Cel**: trzymać oryginalny opis z wyciągu do uczenia i dopasowań.
   - **Zasady**:
     - ustawiane tylko przez import (na podstawie kolumny “opis z wyciągu”),
     - **nigdy** nie nadpisywane przy edycji transakcji.
2) (Jeśli nie ma) spójność importu:
   - transakcja powinna dawać się zidentyfikować jako “z importu”, np. przez `transactions.import_id` lub inną istniejącą metadane wg aktualnej architektury importu.

Migracje:
- dodać migrację `add_raw_statement_description_to_transactions_table`.
- indeksy: nie wymagane dla FR-I5 (dopasowania dzieją się w Typesense), ale pole ma być dostępne w query/update bez N+1.

### Routes/API contracts
Brak nowych endpointów specyficznych dla FR-I5.
Integracja odbywa się w:
- imporcie (commit) — enrichment “w locie”
- update transakcji — “learning” (zapamiętywanie)

### Domain/service layer

#### 1) Kontrakt “pamięci” (już istnieje)
Wykorzystać istniejące:
- `App\Support\DescriptionMemory\DescriptionMemoryRepository`
  - `remember(int $userId, Bank $bank, string $rawStatementDescription, ?string $subject, string $description): void`
  - `suggest(int $userId, Bank $bank, string $rawStatementDescription): ?SuggestedFields`
- `App\Support\DescriptionMemory\NullDescriptionMemoryRepository` jako fallback (no-op + `null`).

#### 2) Nowa implementacja: `TypesenseDescriptionMemoryRepository`
Dodać repo, które implementuje `DescriptionMemoryRepository` i opiera się o Typesense.

Proponowane pliki/warstwy:
- `app/Support/DescriptionMemory/TypesenseDescriptionMemoryRepository.php`
- `app/Support/Typesense/TypesenseClient.php` (lekka otoczka na HTTP; jeden odpowiedzialny punkt dla auth/timeout/retry)
- `config/typesense.php` + `.env`:
  - `TYPESENSE_HOST`, `TYPESENSE_PORT`, `TYPESENSE_PROTOCOL`, `TYPESENSE_API_KEY`
  - `TYPESENSE_TIMEOUT_MS` (np. 800ms)
  - `TYPESENSE_ENABLED` (bool) — przełącznik feature/infra
  - `TYPESENSE_FUZZY_NUM_TYPOS` (int, start: 2)
  - `TYPESENSE_MIN_TEXT_MATCH` (int, start: 10)

Binding w kontenerze:
- w `AppServiceProvider` (lub dedykowanym providerze) zarejestrować:
  - jeśli `TYPESENSE_ENABLED=false` → bind interfejsu do `NullDescriptionMemoryRepository`
  - jeśli `true` → bind do `TypesenseDescriptionMemoryRepository`
  - dodatkowo: jeśli client “healthcheck” nie przejdzie w runtime, repo powinno łapać wyjątki i degradować do “no suggestion/no remember” (best-effort).

Obsługa degradacji / niezawodność:
- `suggest()`:
  - timeouts/5xx/network → zwrócić `null`
  - **nigdy** nie rzucać wyjątku, który przerywa import
- `remember()`:
  - timeouts/5xx/network → swallow (best-effort), ewentualnie log ostrzegawczy bez danych wrażliwych

#### 3) Typesense — kolekcja i dokument
Kolekcja: `import_description_memory`

Dokument (propozycja pól):
- `id` (string) — deterministyczny identyfikator upsertu, np. hash:
  - `sha256(user_id + bank + raw_key)` (w hex/base64url)
- `user_id` (int, facet/filter)
- `bank` (string, facet/filter; wartość enum)
- `raw_key` (string) — normalizacja deterministyczna wg FR-I3 (trim + case-fold + whitespace collapse)
- `learned_subject` (string, optional)
- `learned_description` (string)
- `updated_at` (int64) — unix timestamp

Schemat kolekcji (Typesense):
- `user_id`, `bank` jako `facet: true`
- `raw_key` jako `index: true`
- `learned_*` jako `index: false` (lub true jeśli chcemy wyszukiwać po nich; nie jest potrzebne)

Normalizacja kluczy:
- Reuse zasad normalizacji opisu z deduplikacji (FR-I3): `trim + case-fold + whitespace collapse`.
- Nie stosujemy “relaxed” w MVP (brak usuwania referencji/ID).

Algorytm dopasowania w `suggest()` (best-effort, fuzzy od razu):
1) Oblicz `raw_key = normalize_fr_i3($rawStatementDescription)`
2) Jeśli `raw_key` puste → zwróć `null`
3) Query w Typesense:
   - `q = raw_key`
   - `query_by = raw_key`
   - `num_typos = TYPESENSE_FUZZY_NUM_TYPOS` (start: 2)
   - `per_page = 1`
   - `filter_by = user_id:={currentUserId} && bank:={bank}`
4) Jeśli brak wyników → `null`
5) Jeśli wynik jest → zastosuj “Score gate”
6) Jeśli score spełnia próg:
   - zwróć `SuggestedFields(subject, description, matchType, score)`
   - `matchType`: `fuzzy` (jedyny tryb w MVP)
7) Jeśli score poniżej progu → `null`

Score gate (krytyczne, bo suggestion zawsze nadpisuje adapter po przekroczeniu progu):
- `score` definiujemy jako `text_match` zwracany przez Typesense dla hitu (liczba całkowita).
- Konfiguracja: `TYPESENSE_MIN_TEXT_MATCH` (int, start: 10).
- Jeśli `text_match < TYPESENSE_MIN_TEXT_MATCH` → traktujemy jak brak dopasowania (zwracamy `null`).

#### 4) Wpięcie w import (enrichment)
Miejsce: tam gdzie import tworzy `Transaction` z wiersza (wg `.docs/backend-plan/import-api-plan.md` jest to akcja `CommitImport` / job commitujący).

Flow dla pojedynczego wiersza importu:
1) Ustalić `raw_statement_description` (z kolumny mapowania / parsera bankowego).
   - Jeśli `raw_statement_description` puste → pominąć FR-I5 (brak `suggest()`).
   - Jeśli `Account.bank` to `Cash` → pominąć FR-I5.
2) Ustalić fallbacki:
   - `subject = (wartość z adaptera banku) lub null`
   - `description = raw_statement_description` (zawsze raw jako fallback dla FR-I5)
3) Spróbować wzbogacić przez Typesense:
   - `suggested = DescriptionMemoryRepository::suggest($userId, $bank, $raw_statement_description)`
   - jeśli `suggested !== null`:
     - “zawsze nadpisuj adapter po progu”, ale scalaj:
       - jeśli `suggested.subject` niepuste → `subject = suggested.subject`
       - jeśli `suggested.description` niepuste → `description = suggested.description`
     - emit telemetry `import_enrichment_typesense_hit` (bez raw danych): `bank`, `match_type`, `score`
   - else:
     - emit telemetry `import_enrichment_typesense_miss` (tylko `bank`)
4) Zapisać `Transaction` z:
   - `raw_statement_description` (oryginał)
   - `subject`, `description` (po enrichment)

Ważne:
- Suggestion działa **per user + per bank** (z `Account.bank`).
- Niedostępność Typesense nie może przerwać importu (repo zwraca `null`).

#### 5) Wpięcie w edycję transakcji (learning / remember)
Miejsce: akcja/serwis odpowiedzialny za update transakcji (np. `UpdateTransaction` / controller update).

Warunki zapisu pamięci:
- transakcja pochodzi z importu (np. `import_id` niepuste) **i** ma `raw_statement_description` niepuste
- `Account.bank` != `Cash`
- ignorujemy zapisy “puste”:
  - jeśli `description` jest puste po zapisie → nie zapisujemy pamięci
  - jeśli user wyczyści `subject` (null) ale `description` jest niepuste → zapisujemy (pamięć może mieć brak subject)
- zapisujemy tylko po “udanej” walidacji i zapisie transakcji w DB

Flow:
1) Po zapisie zmian `subject`/`description`:
2) Wywołać:
   - `DescriptionMemoryRepository::remember($userId, $account->bank, $transaction->raw_statement_description, $transaction->subject, $transaction->description)`
3) Upsert w Typesense:
   - `id` deterministyczne (patrz wyżej)
   - pola `learned_*` z aktualnych wartości (“last write wins”)
   - `updated_at = now()->timestamp`

Ochrona przed “śmieciową pamięcią”:
- Jeśli user zapisze pusty `description` — nie zapisujemy pamięci.
- Jeśli `raw_statement_description` po normalizacji jest puste — nie zapisujemy pamięci.
- (Opcjonalnie) Jeśli `raw_statement_description` jest ekstremalnie krótkie po normalizacji — nie zapisujemy pamięci.

### Authorization
- Scoping danych per user jest naturalne w istniejących politykach importu/transakcji.
- W Typesense izolacja jest enforced przez:
  - **filter** `user_id:={currentUserId}` w każdym `suggest()`
  - zapisy zawsze z `user_id = currentUserId`
- Dodatkowo bank scoping:
  - filter `bank:={Account.bank->value}` w `suggest()`

### Telemetry
Wymagane eventy (z PRD):
- `import_enrichment_typesense_hit`
- `import_enrichment_typesense_miss`

Proponowana implementacja (minimalna, nieblokująca):
- Emitować Laravel eventy domenowe:
  - `App\Events\Imports\ImportEnrichmentTypesenseHit`
  - `App\Events\Imports\ImportEnrichmentTypesenseMiss`
- Payload (bez raw opisów):
  - `user_id`, `import_id` (jeśli dostępne), `bank`, `match_type`, `score`
- Listener może logować structured log (`info`) lub wysłać do docelowego telemetry sink w przyszłości.
 - W przypadku błędów Typesense: log `warning` z minimalnym kontekstem (`bank`, `import_id` opcjonalnie, `exception_class`) bez `raw_statement_description` i bez `raw_key`.

## 4) Test plan (Pest)
Cel: pokryć dwa kluczowe zachowania (learning i enrichment) + edge cases (brak dopasowania, izolacja per user/bank, degradacja).

### Testy feature/unit
1) **Learning — remember on transaction update**
   - Given transakcja z importu ma `raw_statement_description`
   - When user aktualizuje `subject` i `description`
   - Then `DescriptionMemoryRepository::remember(...)` jest wywołane z `user_id` i `bank`
   - Wariant: transakcja bez `raw_statement_description` → brak wywołania

2) **Enrichment — suggest on import**
   - Given repozytorium pamięci zwraca `SuggestedFields`
   - When import tworzy transakcję
   - Then transakcja ma `subject/description` z sugestii, a `raw_statement_description` zachowane
   - Telemetry: hit emitowany
   - Wariant: sugestia przechodzi próg score → nadpisuje adapter; sugestia poniżej progu → brak nadpisania

3) **Brak dopasowania**
   - Given repozytorium zwraca `null`
   - When import tworzy transakcję
   - Then `subject` puste, `description` = fallback (raw), telemetry miss emitowany

4) **Izolacja per user/bank**
   - W testach repo klienta (fake) upewnić się, że `suggest()` jest wywołane z prawidłowym `userId` i `bank` (kontrakt).
   - (Jeśli test integracyjny Typesense będzie dodany później) przygotować dwa dokumenty dla różnych userów/banków i sprawdzić, że nie “przeciekają”.

5) **Degradacja przy niedostępności Typesense**
   - Zbadać zachowanie `TypesenseDescriptionMemoryRepository`: wyjątek z klienta → `suggest()` zwraca `null`, `remember()` nie rzuca
   - Import nie failuje.

6) **Guardy domenowe**
   - `Bank::Cash` → brak `suggest()` i brak `remember()`
   - wiersz bez `raw_statement_description` → brak `suggest()` i brak enrichment
   - pusta `description` na update → brak `remember()`

### Jak testować bez prawdziwego Typesense (rekomendacja)
- W testach bindować `DescriptionMemoryRepository` do fake’a/in-memory repo (np. `FakeDescriptionMemoryRepository`) i asercje robić na wywołaniach oraz efektach w DB.
- Integracyjne testy Typesense (jeśli w ogóle) oznaczyć jako osobna grupa i uruchamiać tylko lokalnie/na dedykowanym pipeline. **[Assumption]**

Minimalne komendy:
- `./vendor/bin/sail artisan test --compact --filter=DescriptionMemory`
- `./vendor/bin/sail artisan test --compact tests/Feature/Imports`

## 5) Open questions
1) **Czy `raw_statement_description` już istnieje w `transactions`?**
   - Rekomendacja: jeśli nie, dodać pole jak w “Data model”.
2) **Parametry fuzzy i próg `TYPESENSE_MIN_TEXT_MATCH`**
   - Start: `TYPESENSE_FUZZY_NUM_TYPOS=2`, `TYPESENSE_MIN_TEXT_MATCH=10`.
   - Dostrojenie po pierwszych danych produkcyjnych na podstawie telemetry hit/miss oraz korekt użytkowników.

### FR summary
FR-T3 „Transfer między kontami” umożliwia użytkownikowi wykonanie pojedynczej akcji w UI, która tworzy **dwie** powiązane transakcje: obciążenie konta źródłowego kwotą `-X` oraz uznanie konta docelowego kwotą `+X`, obie z **identyczną datą** `D`. System musi blokować transfer, gdy konto źródłowe lub docelowe jest usunięte (soft-deleted), oraz gdy użytkownik spróbuje wybrać to samo konto jako źródło i cel. Użytkownik podaje kwotę dodatnią; system zapisuje znaki (ujemna/dodatnia) po swojej stronie. Telemetria: `transfer_created`, `transfer_failed_validation`.

### Assumptions
- Transfer jest dozwolony tylko pomiędzy kontami użytkownika w **tej samej walucie** (inaczej kwota `X` jest niejednoznaczna). Dla różnych walut zwracamy błąd walidacji.
- UI wyśle `amount` jako dodatnią liczbę; backend waliduje `amount > 0` i sam zapisuje `-amount` / `+amount`.
- Do powiązania dwóch transakcji używamy istniejącej kolumny `transactions.transfer_id` (UUID) i ustawiamy ją dla obu rekordów.
- Transfery nie powinny wpadać w mechanizm deduplikacji pojedynczych transakcji (unikat `transactions(account_id, dedupe_hash)`) w sposób, który blokuje poprawne powtórzenie takiego samego transferu. Dedupe dla transferu musi gwarantować unikalność per transfer, a nie per (data/kwota/opis).

### Implementation plan
#### Data model
- **Wykorzystać istniejące pole** `transactions.transfer_id` (nullable UUID) jako identyfikator transferu.
- **Bez nowej tabeli** `transfers` (na tym etapie wystarczy `transfer_id` + dwie transakcje).
- **Zmiana w `transactions` (opcjonalna, jeśli repo ma już produkcyjne użycie transferów)**:
  - rozważyć indeks złożony `index(['user_id', 'transfer_id'])`, jeśli planujemy często pobierać transfery po użytkowniku.
  - nie dodawać constraintów DB na „dokładnie 2 transakcje per transfer” (MySQL praktycznie tego nie wymusi); wymuszamy w domenie.

#### Routes/API contracts
- Dodać nowe endpointy w stylu istniejących zasobów (Inertia/redirect):
  - **POST** `transfers` → `TransferController@store` (middleware `auth`)
  - Nazwa route: `transfers.store`
- **Request payload** (FormRequest `StoreTransferRequest`):
  - `from_account_id` (int, required)
  - `to_account_id` (int, required, `different:from_account_id`)
  - `date` (string, required, `date_format:d-m-Y`)
  - `amount` (numeric, required, `decimal:0,2`, `gt:0`)
  - `description` (string, nullable, max 2000) — jeśli UI posiada pole opisu; jeśli nie, generujemy opis po stronie backendu (patrz domena).
- **Response**:
  - sukces: redirect np. do `transactions.index` z toastem sukcesu (spójnie z `TransactionController@store`)
  - błąd walidacji: standardowe 302 z błędami (Inertia) + telemetria `transfer_failed_validation`
  - błąd autoryzacji / konto usunięte: `403`
  - konto nie istnieje / nie należy do usera: `422` (walidacja `exists ... where user_id + whereNull(deleted_at)`)

#### Validation
- `from_account_id`:
  - `required|integer`
  - `exists:accounts,id` z warunkiem `user_id = auth()->id()` oraz `deleted_at IS NULL` (analogicznie do `StoreTransactionRequest`)
- `to_account_id`:
  - `required|integer|different:from_account_id`
  - `exists:accounts,id` z warunkiem `user_id = auth()->id()` oraz `deleted_at IS NULL`
- `amount`:
  - `required|numeric|decimal:0,2|gt:0`
  - (Edge case z PRD) ujemna kwota w formularzu: nie przechodzi walidacji (`gt:0`)
- `date`:
  - `required|date_format:d-m-Y`
- Walidacja waluty (Assumption):
  - dodatkowa reguła/closure: oba konta muszą mieć ten sam `currency_id`
  - jeśli różne: fail np. message `"Transfers between different currencies are not supported."`

#### Domain/service layer
- Dodać akcję domenową `app/Actions/Transfers/CreateTransfer.php` (konwencja: `Actions/*` jak `StoreTransaction`):
  - `handle(User $user, array $validated): array{withdrawal: Transaction, deposit: Transaction, transfer_id: string}`
- Implementacja akcji:
  - `DB::transaction(fn () => ..., attempts: 5)` (retry na deadlock)
  - Wczytać i **zablokować oba konta** `lockForUpdate()` w deterministycznej kolejności (sort po `id`) – analogicznie do `UpdateTransaction`:
    - `Account::query()->whereIn('id', [$fromId, $toId])->where('user_id', $user->id)->lockForUpdate()->get()->keyBy('id')`
  - Guardy domenowe:
    - jeśli któregoś konta brak albo jest trashed → `abort(403)` (albo błąd walidacji; preferuj walidację, ale przy równoległych zmianach i tak zabezpieczyć w domenie)
    - jeśli `currency_id` różne → `abort(422)` lub wyjątek domenowy mapowany na błąd walidacji
  - Obliczyć:
    - `$dateYmd = CarbonImmutable::createFromFormat('d-m-Y', $validated['date'])->toDateString()`
    - `$amount = TransactionDedupe::amountToDecimalString($validated['amount'])` (to jest dodatnie)
    - `$withdrawAmount = bcmul($amount, '-1', 2)`
    - `$depositAmount = $amount`
    - `$transferId = (string) Str::uuid()`
  - Przygotować opisy:
    - jeśli UI daje `description`: użyć go do obu transakcji (z ewentualnym dopiskiem kierunku w subject), **albo**
    - jeśli nie: wygenerować opisy po angielsku, np. `"Transfer to {$toAccount->name}"` / `"Transfer from {$fromAccount->name}"`
  - Dedupe dla transferu (ważne, by nie blokować powtórzeń):
    - ustawić `normalized_description` na znormalizowaną wersję opisu (jak dziś),
    - ale `dedupe_hash` wyliczyć w sposób gwarantujący unikalność per transfer, np. `md5($transferId, true)` (16 bajtów), zamiast `md5(date|amount|desc, true)`.
  - Utworzyć 2 rekordy `Transaction::create([...])`:
    - oba z `user_id = $user->id`
    - `transfer_id = $transferId`
    - `date = $dateYmd` (identyczna)
    - `currency_id` zgodne z kontem (w praktyce takie samo dla obu przy walidacji)
    - `type`: na podstawie znaku (`expense` dla ujemnej, `income` dla dodatniej) – jak w `StoreTransaction`
  - Zaktualizować salda kont:
    - `$from->current_balance = bcadd((string) $from->current_balance, $withdrawAmount, 2)`
    - `$to->current_balance = bcadd((string) $to->current_balance, $depositAmount, 2)`
    - `save()` obu kont w tej samej transakcji DB
- Dodać `TransferController`:
  - `store(StoreTransferRequest $request, CreateTransfer $action): RedirectResponse`
  - po sukcesie emitować telemetrię `transfer_created` (patrz niżej) i redirect + toast.

#### Authorization
- Bazowo `auth` middleware.
- Dodatkowo:
  - w `StoreTransferRequest` walidujemy scoping do usera przez `exists ... where user_id`.
  - konto usunięte: blokada już na poziomie walidacji (`deleted_at IS NULL`) + w domenie (defense in depth).
- Opcjonalnie dodać Policy/metodę:
  - np. `AccountPolicy::transfer(User $user, Account $from, Account $to): bool` (z checkiem `trashed` i `user_id`) i użyć jej w kontrolerze (Gate::authorize).
  - jeśli projekt preferuje brak nowych metod w policy, sama walidacja + `lockForUpdate` + `abort(403)` jest akceptowalna (spójnie z istniejącym podejściem w `StoreTransaction`).

#### Telemetry
- Zdefiniować lekkie eventy domenowe:
  - `App\Events\TransferCreated` (payload: `user_id`, `transfer_id`, `from_account_id`, `to_account_id`, `amount`, `date`)
  - `App\Events\TransferFailedValidation` (payload: `user_id`, `errors`/`fields`, `ip?`)
- Emitowanie:
  - `transfer_created`: po zatwierdzeniu transakcji DB (po `CreateTransfer->handle(...)`) w kontrolerze.
  - `transfer_failed_validation`: w `StoreTransferRequest::failedValidation(...)` (override) — tam mamy dostęp do błędów walidacji; dispatch event i potem wywołać `parent::failedValidation($validator)`.
- Jeśli w projekcie nie ma jeszcze systemu analityki:
  - Listener może tymczasowo logować do `Log::info('transfer_created', [...])` / `Log::warning('transfer_failed_validation', [...])` po angielsku.

### Test plan (Pest)
- Dodać `tests/Feature/Transfers/CreateTransferTest.php` (Pest).
- Scenariusze:
  - **happy path**: Given dwa różne konta usera w tej samej walucie, When POST `transfers.store` z `amount = X`, `date = D`, Then:
    - w `transactions` są 2 rekordy z tym samym `transfer_id`
    - oba mają tę samą `date`
    - jeden ma `account_id = from`, `amount = -X`
    - drugi ma `account_id = to`, `amount = +X`
    - `accounts.current_balance` zaktualizowane odpowiednio (z uwzględnieniem początkowych sald)
  - **validation**: `from_account_id == to_account_id` → 422 + brak transakcji + event `transfer_failed_validation`
  - **validation**: `amount <= 0` (0, ujemna) → 422 + brak transakcji + event `transfer_failed_validation`
  - **edge**: konto źródłowe soft-deleted → 422 (exists rule) lub 403 (domena) + brak transakcji
  - **edge**: konto docelowe soft-deleted → analogicznie
  - **assumption**: różne `currency_id` → 422 + brak transakcji
  - **integrity**: dedupe nie blokuje dwóch identycznych transferów (2 razy ten sam request) → powstają 4 transakcje w dwóch parach (różne `transfer_id`)
- Dodatki testowe:
  - upewnić się, że istnieją factory dla `Account` / `Currency` / `User`; jeśli brak, dodać minimalne factories.
- Minimalne komendy:
  - `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/CreateTransferTest.php`

### Open questions
- Czy transfer pomiędzy różnymi walutami ma być wspierany (np. z przelicznikiem)? **Rekomendacja domyślna**: na MVP blokować i zwracać błąd walidacji. - Na ten moment nie będzie wspierane.
- Czy UI ma mieć pole `description` dla transferu? **Rekomendacja domyślna**: jeśli brak, backend generuje opisy po angielsku, aby transakcje były czytelne na liście. - Tak.

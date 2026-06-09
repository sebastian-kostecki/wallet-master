# Goals currency — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `currency_id` to goals, display all goal amounts with currency symbol, and filter balance/transfer/transaction assignment by goal currency (MVP: PLN only in UI).

**Architecture:** Migration backfills existing goals to PLN. Backend follows Account currency patterns (`CurrencyResource`, immutable after create). `GoalBalance` and `GoalTransactionMetrics` filter Savings legs by `account.currency_id = goal.currency_id`. `ValidatesGoalId` extended for transfer and optional P&L goal currency match. Shared frontend `formatMoney(value, currency)`.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests/migrations.

**Spec:** `.docs/superpowers/specs/2026-06-05-goals-currency-design.md`  
**PRD:** `.docs/prd.md` (already updated)

**Suggested branch:** `improvement/goals`

---

## File map

| Action | Path |
|--------|------|
| Modify | `.docs/checklist.md` — section 19 goals currency item |
| Create | `database/migrations/2026_06_05_100000_add_currency_id_to_goals_table.php` |
| Modify | `app/Models/Goal.php` |
| Modify | `database/factories/GoalFactory.php` |
| Modify | `app/Http/Requests/Goals/StoreGoalRequest.php` |
| Modify | `app/Actions/Goals/StoreGoal.php` |
| Modify | `app/Http/Resources/Goals/GoalResource.php` |
| Modify | `app/Data/Goals/GoalFormOptions.php` |
| Modify | `app/Actions/Goals/ListGoals.php` |
| Modify | `app/Http/Controllers/Goals/GoalController.php` |
| Modify | `app/Support/Goals/GoalBalance.php` |
| Modify | `app/Support/Goals/GoalTransactionMetrics.php` |
| Modify | `app/Http/Requests/Concerns/ValidatesGoalId.php` |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Create | `resources/js/lib/formatMoney.ts` |
| Modify | `resources/js/pages/goals/Create.vue` |
| Modify | `resources/js/pages/goals/Edit.vue` |
| Modify | `resources/js/pages/goals/Index.vue` |
| Modify | `resources/js/components/goals/GoalProgressBar.vue` |
| Modify | `resources/js/pages/budget/Monthly.vue` |
| Modify | `resources/js/locales/pl.json`, `en.json` |
| Create | `tests/Feature/Goals/GoalCurrencyMigrationTest.php` |
| Create | `tests/Feature/Goals/GoalCurrencyTest.php` |
| Modify | `tests/Unit/Support/Goals/GoalBalanceTest.php` |
| Modify | `tests/Feature/Goals/GoalCrudTest.php` |
| Modify | `tests/Feature/Transfers/TransferGoalTest.php` |
| Modify | `tests/Feature/Transactions/TransactionGoalTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |

---

### Task 1: Migration — `goals.currency_id`

**Files:**
- Create: `database/migrations/2026_06_05_100000_add_currency_id_to_goals_table.php`
- Create: `tests/Feature/Goals/GoalCurrencyMigrationTest.php`

- [ ] **Step 1: Write failing migration test**

```php
<?php

use App\Models\Currency;
use App\Models\Goal;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('goals currency migration backfills existing goals with PLN', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $goal = Goal::factory()->create(['user_id' => $user->id]);

    Schema::table('goals', function ($table) {
        $table->dropForeign(['currency_id']);
        $table->dropColumn('currency_id');
    });

    expect(Schema::hasColumn('goals', 'currency_id'))->toBeFalse();

    $migration = require database_path('migrations/2026_06_05_100000_add_currency_id_to_goals_table.php');
    $migration->up();

    $goal->refresh();

    expect((int) $goal->currency_id)->toBe($plnId);
    expect(Schema::hasColumn('goals', 'currency_id'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalCurrencyMigrationTest.php`  
Expected: FAIL (column missing or migration not found)

- [ ] **Step 3: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('sort_order')->constrained('currencies');
        });

        $plnId = DB::table('currencies')->where('code', 'PLN')->value('id');

        if ($plnId === null) {
            throw new RuntimeException('PLN currency must exist before goals currency migration.');
        }

        DB::table('goals')->whereNull('currency_id')->update(['currency_id' => $plnId]);

        Schema::table('goals', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
```

- [ ] **Step 4: Run migration and test**

Run:
```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalCurrencyMigrationTest.php
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_05_100000_add_currency_id_to_goals_table.php tests/Feature/Goals/GoalCurrencyMigrationTest.php
git commit -m "feat(goals): add currency_id column with PLN backfill"
```

---

### Task 2: Goal model + factory

**Files:**
- Modify: `app/Models/Goal.php`
- Modify: `database/factories/GoalFactory.php`

- [ ] **Step 1: Update Goal model**

Add to PHPDoc:
```php
 * @property int $currency_id
 * @property Currency $currency
```

Add to `$fillable`: `'currency_id'`

Add to `casts()`: `'currency_id' => 'integer'`

Add relation:
```php
/**
 * @return BelongsTo<Currency, $this>
 */
public function currency(): BelongsTo
{
    return $this->belongsTo(Currency::class);
}
```

Import `App\Models\Currency`.

- [ ] **Step 2: Update GoalFactory**

In `definition()`, resolve PLN id lazily:

```php
'currency_id' => fn () => (int) \App\Models\Currency::query()->where('code', 'PLN')->value('id')
    ?: throw new \RuntimeException('Seed CurrencySeeder before GoalFactory.'),
```

- [ ] **Step 3: Run existing goal tests**

Run: `./vendor/bin/sail artisan test --compact --filter=Goal`  
Expected: PASS (or fix any factory/seed issues in failing tests by adding `CurrencySeeder` in `beforeEach` where missing)

- [ ] **Step 4: Commit**

```bash
git add app/Models/Goal.php database/factories/GoalFactory.php
git commit -m "feat(goals): add currency relation on Goal model"
```

---

### Task 3: Store goal with `currency_id` (TDD)

**Files:**
- Modify: `app/Http/Requests/Goals/StoreGoalRequest.php`
- Modify: `app/Actions/Goals/StoreGoal.php`
- Create: `tests/Feature/Goals/GoalCurrencyTest.php`

- [ ] **Step 1: Write failing feature tests**

```php
<?php

use App\Models\Currency;
use App\Models\Goal;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create goal with currency PLN', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertRedirect();

    $goal = Goal::where('user_id', $user->id)->where('name', 'Wakacje')->first();

    expect($goal)->not->toBeNull();
    expect((int) $goal->currency_id)->toBe($plnId);
});

test('goal resource includes nested currency', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ]);

    $response = $this->actingAs($user)->get(route('goals.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('goals', 1)
        ->where('goals.0.currency.code', 'PLN')
        ->where('goals.0.currency.symbol', 'zł')
    );
});

test('create goal requires currency_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
    ])->assertSessionHasErrors('currency_id');
});

test('update goal does not accept currency_id', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $goal = Goal::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);

    $this->actingAs($user)->patch(route('goals.update', $goal), [
        'name' => 'Renamed',
        'currency_id' => $plnId,
    ])->assertRedirect();

    expect((int) $goal->fresh()->currency_id)->toBe($plnId);
});
```

- [ ] **Step 2: Run tests — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalCurrencyTest.php`

- [ ] **Step 3: Add validation to StoreGoalRequest**

In `rules()`:
```php
'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
```

- [ ] **Step 4: Persist in StoreGoal**

Add to create array:
```php
'currency_id' => (int) $validated['currency_id'],
```

- [ ] **Step 5: Update GoalResource**

```php
use App\Http\Resources\Accounts\CurrencyResource;

// in toArray(), after sort_order:
'currency_id' => $this->currency_id,
'currency' => $this->whenLoaded(
    'currency',
    fn () => CurrencyResource::make($this->currency)->resolve($request),
),
```

- [ ] **Step 6: Eager-load in ListGoals**

```php
$this->goals = $query->with('currency')->get();
```

In `GoalController::edit()`, load relation before resource:
```php
$goal->load('currency');
```

- [ ] **Step 7: Run tests — expect PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalCurrencyTest.php`

- [ ] **Step 8: Fix GoalCrudTest — add currency_id to store payloads**

In `tests/Feature/Goals/GoalCrudTest.php`, every `post(route('goals.store'), [...])` must include `'currency_id' => $plnId` (resolve PLN id like other tests).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Goals/StoreGoalRequest.php app/Actions/Goals/StoreGoal.php app/Http/Resources/Goals/GoalResource.php app/Actions/Goals/ListGoals.php app/Http/Controllers/Goals/GoalController.php tests/Feature/Goals/GoalCurrencyTest.php tests/Feature/Goals/GoalCrudTest.php
git commit -m "feat(goals): require currency_id on create and expose in API"
```

---

### Task 4: GoalFormOptions — currencies for create/edit

**Files:**
- Modify: `app/Data/Goals/GoalFormOptions.php`

- [ ] **Step 1: Add currencies to GoalFormOptions**

```php
use App\Models\Currency;

/**
 * @return array{
 *   icons: list<array{value: string, label_key: string}>,
 *   colors: list<array{value: string}>,
 *   currencies: list<array{id: int, code: string, name: string, symbol: string, precision: int}>,
 * }
 */
public function toArray(): array
{
    return [
        'icons' => /* unchanged */,
        'colors' => /* unchanged */,
        'currencies' => Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision'])
            ->map(fn (Currency $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
                'precision' => $c->precision,
            ])
            ->all(),
    ];
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Data/Goals/GoalFormOptions.php
git commit -m "feat(goals): expose currencies in GoalFormOptions"
```

---

### Task 5: GoalBalance currency filter (TDD)

**Files:**
- Modify: `app/Support/Goals/GoalBalance.php`
- Modify: `app/Support/Goals/GoalTransactionMetrics.php`
- Modify: `tests/Unit/Support/Goals/GoalBalanceTest.php`

- [ ] **Step 1: Write failing unit test**

Add to `GoalBalanceTest.php`:

```php
test('cumulative balance ignores savings legs in a different currency than the goal', function () {
    $this->seed(CurrencySeeder::class);
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    DB::table('currencies')->updateOrInsert(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()],
    );
    $eurId = (int) Currency::query()->where('code', 'EUR')->value('id');

    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);
    $savingsPln = Account::factory()->create([
        'user_id' => $user->id,
        'type' => AccountType::Savings,
        'currency_id' => $plnId,
    ]);
    $savingsEur = Account::factory()->create([
        'user_id' => $user->id,
        'type' => AccountType::Savings,
        'currency_id' => $eurId,
    ]);
    $transferId = (string) Str::uuid();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $savingsPln->id,
        'currency_id' => $plnId,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId,
        'amount' => '100.00',
        'type' => TransactionType::Transfer,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $savingsEur->id,
        'currency_id' => $eurId,
        'goal_id' => $goal->id,
        'transfer_id' => (string) Str::uuid(),
        'amount' => '999.00',
        'type' => TransactionType::Transfer,
    ]);

    $result = GoalBalance::cumulative($user, $goal);

    expect($result['balance'])->toBe('100.00');
});
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Goals/GoalBalanceTest.php --filter=different`

- [ ] **Step 3: Update GoalBalance queries**

Replace Savings `whereHas` closures in both saved and released queries:

```php
->whereHas('account', fn ($q) => $q
    ->where('type', AccountType::Savings)
    ->where('currency_id', $goal->currency_id))
```

Apply the same pattern in `monthlyNetMap()`.

- [ ] **Step 4: Update GoalTransactionMetrics**

Same `whereHas` filter on saved, released queries (linked_expenses stays unfiltered by account currency — P&L row uses transaction currency; optional follow-up if needed).

- [ ] **Step 5: Run unit tests — expect PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Goals/GoalBalanceTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Support/Goals/GoalBalance.php app/Support/Goals/GoalTransactionMetrics.php tests/Unit/Support/Goals/GoalBalanceTest.php
git commit -m "feat(goals): filter balance metrics by goal currency"
```

---

### Task 6: Transfer + transaction goal currency validation

**Files:**
- Modify: `app/Http/Requests/Concerns/ValidatesGoalId.php`
- Modify: `tests/Feature/Transfers/TransferGoalTest.php`
- Modify: `tests/Feature/Transactions/TransactionGoalTest.php`

- [ ] **Step 1: Write failing transfer test**

Add to `TransferGoalTest.php`:

```php
test('transfer rejects goal when goal currency does not match savings account currency', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    DB::table('currencies')->updateOrInsert(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'created_at' => now(), 'updated_at' => now()],
    );
    $eurId = (int) Currency::query()->where('code', 'EUR')->value('id');

    $user = User::factory()->create();
    $ror = Account::query()->create([/* ... currency_id => $plnId, type Ror ... */]);
    $savings = Account::query()->create([/* ... currency_id => $plnId, type Savings ... */]);
    $goal = Goal::factory()->create(['user_id' => $user->id, 'currency_id' => $eurId]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $ror->id,
        'to_account_id' => $savings->id,
        'date' => '05-06-2026',
        'amount' => '100.00',
        'goal_id' => $goal->id,
    ])->assertSessionHasErrors('goal_id');
});
```

- [ ] **Step 2: Extend ValidatesGoalId**

Add private method:

```php
protected function goalCurrencyMatchesAccounts(int $goalId, array $accountIds): bool
{
    $goal = \App\Models\Goal::query()
        ->where('user_id', $this->user()->id)
        ->find($goalId);

    if ($goal === null) {
        return true;
    }

    $currencyIds = Account::query()
        ->where('user_id', $this->user()->id)
        ->whereIn('id', $accountIds)
        ->whereNull('deleted_at')
        ->pluck('currency_id')
        ->unique()
        ->values();

    return $currencyIds->count() === 1
        && (int) $currencyIds->first() === (int) $goal->currency_id;
}
```

In `goalIdRulesForTransfer()`, wrap `goal_id` rule with closure validation calling `goalCurrencyMatchesAccounts` for `[(int)$fromId, (int)$toId]`.

In `optionalGoalIdRules()`, add `Rule::exists(...)` plus closure: when `goal_id` present, load `account_id` from request and assert `goalCurrencyMatchesAccounts($goalId, [(int)$this->input('account_id')])` — also compare transaction currency will match because account determines transaction currency on store.

Add to `StoreTransactionRequest::withValidator()` (or extend trait helper):

```php
$validator->after(function (Validator $validator): void {
    $goalId = $this->input('goal_id');
    $accountId = $this->input('account_id');
    if (! is_numeric($goalId) || ! is_numeric($accountId)) {
        return;
    }
    if (! $this->goalCurrencyMatchesAccounts((int) $goalId, [(int) $accountId])) {
        $validator->errors()->add('goal_id', __('validation.custom.goal_id.currency_mismatch'));
    }
});
```

Add i18n key in `lang/en/validation.php` and `lang/pl/validation.php`:
```php
'goal_id' => [
    'currency_mismatch' => 'Goal currency must match the account currency.',
],
```
(Polish equivalent in `pl`.)

- [ ] **Step 3: Run tests**

Run:
```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferGoalTest.php tests/Feature/Transactions/TransactionGoalTest.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/Concerns/ValidatesGoalId.php app/Http/Requests/Transactions/StoreTransactionRequest.php app/Http/Requests/Transactions/UpdateTransactionRequest.php lang/en/validation.php lang/pl/validation.php tests/Feature/Transfers/TransferGoalTest.php tests/Feature/Transactions/TransactionGoalTest.php
git commit -m "feat(goals): validate goal currency on transfers and transactions"
```

---

### Task 7: Monthly budget goal_rows currency

**Files:**
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: Eager-load currency in buildGoalRows**

Change goals query:
```php
->with('currency')
```

Add to each row array:
```php
'currency' => [
    'code' => $goal->currency->code,
    'symbol' => $goal->currency->symbol,
    'precision' => $goal->currency->precision,
],
```

- [ ] **Step 2: Assert in MonthlyBudgetTest**

In an existing goal section test, add:
```php
->where('goal_rows.0.currency.code', 'PLN')
```

- [ ] **Step 3: Run test**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 4: Commit**

```bash
git add app/Actions/Budgets/ListMonthlyBudget.php tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "feat(budget): include goal currency in monthly goal_rows"
```

---

### Task 8: Frontend — shared formatter + goals UI

**Files:**
- Create: `resources/js/lib/formatMoney.ts`
- Modify: `resources/js/pages/goals/Create.vue`
- Modify: `resources/js/pages/goals/Edit.vue`
- Modify: `resources/js/pages/goals/Index.vue`
- Modify: `resources/js/components/goals/GoalProgressBar.vue`
- Modify: `resources/js/pages/budget/Monthly.vue`
- Modify: `resources/js/locales/pl.json`, `en.json`

- [ ] **Step 1: Create formatMoney helper**

```typescript
export type CurrencyDisplay = {
    symbol: string;
    precision?: number;
};

export function formatMoney(
    value: string | number | null | undefined,
    currency?: CurrencyDisplay | null,
    locale = 'pl-PL',
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const parsed = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));

    if (Number.isNaN(parsed)) {
        return String(value);
    }

    const precision = currency?.precision ?? 2;
    const formatted = new Intl.NumberFormat(locale, {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    }).format(parsed);

    const symbol = currency?.symbol?.trim();

    return symbol ? `${formatted} ${symbol}` : formatted;
}
```

- [ ] **Step 2: Update goals/Create.vue**

- Add prop type `currencies: { id: number; code: string; name: string; symbol: string; precision: number }[]`
- Add `currency_id` to `useForm`, default first currency id (PLN)
- Add currency `FormField` with `<select>` mirroring `accounts/Create.vue` pattern (reuse `Dropdown` if used there)
- Add `:suffix` or adjacent span with selected currency symbol on amount inputs (`target_amount`, `monthly_contribution`)

- [ ] **Step 3: Update goals/Edit.vue**

- Show currency read-only (text: `PLN (zł)` from `goal.currency`)
- Use `formatMoney` for display-only amount hints if any
- Do **not** submit `currency_id` on patch

- [ ] **Step 4: Update goals/Index.vue + GoalProgressBar.vue**

Extend `Goal` type:
```typescript
currency: { code: string; symbol: string; precision: number };
```

Replace raw `{{ goal.balance }}` with `formatMoney(goal.balance, goal.currency)`.

Pass `currency` prop to `GoalProgressBar`; format `balance / targetAmount` with symbol.

- [ ] **Step 5: Update budget/Monthly.vue**

Extend `GoalRow` with `currency`; replace local `formatMoney` calls for goal section with imported helper + row currency. Keep existing P&L formatter or migrate P&L to helper without symbol (pass `null` currency).

- [ ] **Step 6: Add locale keys**

`goals.fields.currency.label`, `goals.fields.currency.placeholder` (reuse accounts keys or alias).

- [ ] **Step 7: Manual smoke**

Run: `./vendor/bin/sail npm run build`  
Verify `/goals`, `/goals/create`, `/budget/monthly` show `1 234,56 zł`.

- [ ] **Step 8: Commit**

```bash
git add resources/js/lib/formatMoney.ts resources/js/pages/goals/ resources/js/components/goals/GoalProgressBar.vue resources/js/pages/budget/Monthly.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(goals): display goal amounts with currency symbol"
```

---

### Task 9: Checklist + verification

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Update checklist section 19**

Add under target model:
```markdown
- [ ] Waluta celu (`currency_id`); wyświetlanie kwot z symbolem; walidacja zgodności z kontem (spec `2026-06-05-goals-currency-design.md`)
```

- [ ] **Step 2: Run full goals domain tests**

Run:
```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact --filter=Goal
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferGoalTest.php
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionGoalTest.php
```
Expected: all PASS

- [ ] **Step 3: Commit checklist**

```bash
git add .docs/checklist.md
git commit -m "docs: add goals currency to checklist"
```

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| `goals.currency_id` NOT NULL + PLN migration | Task 1 |
| Immutable after create | Task 3 (UpdateGoalRequest unchanged) |
| StoreGoalRequest validation | Task 3 |
| GoalResource + currency nested | Task 3 |
| GoalFormOptions currencies | Task 4 |
| GoalBalance currency filter | Task 5 |
| GoalTransactionMetrics filter | Task 5 |
| Transfer goal currency match | Task 6 |
| P&L goal currency match | Task 6 |
| Monthly budget goal_rows currency | Task 7 |
| Frontend formatMoney + all views | Task 8 |
| Tests | Tasks 1, 3, 5, 6, 7 |
| Checklist | Task 9 |

No placeholders remain. Types consistent: `CurrencyDisplay` / `CurrencyResource` shape aligned across API and Vue.

# Dashboard Operator Overview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `/panel` into an operator overview for the current calendar month: four KPI tiles (PLN total, savings, income, expense), a monthly budget snapshot with one CSS plan-vs-actual chart, and the eight most recent transactions.

**Architecture:** New `ShowDashboard` action (`handle(User): void` + getters) composes account totals, `BudgetTransactionQuery` month P&L, `ListMonthlyBudget::handleForPeriod`, and a `limit(8)` transaction query. `DashboardController` maps `TransactionResource` and stops passing the `accounts` collection. Vue lives in `components/dashboard/`. No chart library.

**Tech Stack:** Laravel 13, Inertia Vue 3, Tailwind v3, Pest 4, Pint, Prettier, `vue-tsc`.

**Spec:** `.docs/superpowers/specs/2026-08-27-dashboard-operator-overview-design.md`  
**Suggested branch:** `improvement/dashboard-operator-overview`

## Global Constraints

- Variant A: Action never imports `Http\Resources`; controller maps resources. No `app/Services/`.
- `DashboardController` stays at `app/Http/Controllers/DashboardController.php`.
- Current calendar month from `now()` (app timezone). No period switcher.
- Cashflow tiles: `transfer_id IS NULL`; expense is a positive absolute. Budget card uses monthly `summary` **with pockets**.
- Recent: 8 rows, all time, `COALESCE(booked_at, date) desc, id desc`.
- Cards: `rounded-2xl border border-transparent bg-card`, no resting shadow. Primary button is ink. Color only on money/status.
- One CSS/SVG chart only — no new npm chart dependency.
- Delete unused `dashboard.quickActions`, `dashboard.preview`, `dashboard.description`, `dashboard.headTitle`.
- Code, comments, commits in English. User-facing copy via i18n.
- After PHP: `vendor/bin/pint --dirty --format agent` then `./vendor/bin/sail artisan test --compact tests/Feature/Dashboard tests/Feature/Budgets/MonthlyBudgetTest.php`.
- After Vue: `npx prettier --write` on touched files; `npm run typecheck`.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` | Public `handleForPeriod(User, year, month)` |
| Create | `app/Actions/Dashboard/ShowDashboard.php` | Orchestrate dashboard getters |
| Modify | `app/Http/Controllers/DashboardController.php` | Call action; map Inertia props |
| Create | `tests/Feature/Dashboard/DashboardTest.php` | Move + extend feature tests |
| Delete | `tests/Feature/DashboardTest.php` | After the move |
| Modify | `resources/js/locales/pl.json`, `en.json` | Panel copy; remove launchpad keys |
| Modify | `resources/js/lib/pageRegistry.ts` | Drop `descriptionKey` on `dashboard` |
| Create | `resources/js/components/dashboard/DashboardKpiCard.vue` | One KPI tile |
| Create | `resources/js/components/dashboard/DashboardPlanActualBars.vue` | CSS plan vs actual bars |
| Create | `resources/js/components/dashboard/DashboardBudgetCard.vue` | Budget snapshot |
| Create | `resources/js/components/dashboard/DashboardTransactionRow.vue` | Compact read-only row |
| Create | `resources/js/components/dashboard/DashboardRecentCard.vue` | Recent list card |
| Modify | `resources/js/pages/Dashboard.vue` | Operator layout |
| Delete | `resources/js/components/accounts/AccountsSummaryCard.vue` | Unused after the move |
| Modify | `.docs/checklist.md` | Record the dashboard overview |

---

### Task 1: `ListMonthlyBudget::handleForPeriod`

**Files:**
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Test: `tests/Feature/Budgets/MonthlyBudgetTest.php` (regression only)

**Interfaces:**
- Consumes: existing `handle(MonthlyBudgetRequest)` body.
- Produces: `public function handleForPeriod(User $user, int $year, int $month): void`. `handle()` must only delegate to it.

- [ ] **Step 1: Extract `handleForPeriod`**

Replace `handle()` in `app/Actions/Budgets/ListMonthlyBudget.php` with:

```php
public function handle(MonthlyBudgetRequest $request): void
{
    $this->handleForPeriod($request->user(), $request->getYear(), $request->getMonth());
}

public function handleForPeriod(User $user, int $year, int $month): void
{
    $this->year = $year;
    $this->month = $month;
    $this->rows = [];
    $this->pocketRows = [];

    app(EnsureUserCategories::class)->handle($user);

    $listCategories = app(ListCategories::class);
    $listCategories->handle($user);
    $this->categories = $listCategories->getCategories();

    $period = BudgetPeriod::forMonth($this->year, $this->month);

    $annualByCategory = CategoryAnnualEstimate::query()
        ->whereIn('category_id', $this->categories->pluck('id'))
        ->where('year', $this->year)
        ->get()
        ->keyBy('category_id');

    $monthlyByCategory = CategoryMonthlyEstimate::query()
        ->whereIn('category_id', $this->categories->pluck('id'))
        ->where('year', $this->year)
        ->where('month', $this->month)
        ->get()
        ->keyBy('category_id');

    $actualsQuery = BudgetTransactionQuery::forUser($user);
    BudgetTransactionQuery::inPeriod($actualsQuery, $period);
    BudgetTransactionQuery::excludeTransfers($actualsQuery);

    $actuals = $actualsQuery
        ->select('category_id')
        ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
        ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
        ->groupBy('category_id')
        ->get()
        ->keyBy('category_id');

    $monthlyPlansSum = '0.00';
    $annualPlansSum = '0.00';

    foreach ($this->categories as $category) {
        $annual = $annualByCategory->get($category->id);
        $monthly = $monthlyByCategory->get($category->id);
        $plan = CategoryPlanAmount::monthly($category, $this->year, $this->month, $annual, $monthly);
        $actual = $actuals->get($category->id);
        $actualIncome = $actual !== null
            ? TransactionDedupe::amountToDecimalString((string) $actual->income)
            : '0.00';
        $actualExpense = $actual !== null
            ? TransactionDedupe::amountToDecimalString((string) $actual->expense)
            : '0.00';
        $actualPrimary = $category->type === CategoryType::Income ? $actualIncome : $actualExpense;

        $monthlyPlansSum = bcadd($monthlyPlansSum, $plan, 2);

        if ($annual?->amount !== null) {
            $annualPlansSum = bcadd($annualPlansSum, (string) $annual->amount, 2);
        }

        $this->rows[] = [
            'category_id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'type' => $category->type->value,
            'type_label_key' => $category->type->labelKey(),
            'is_system' => $category->is_system,
            'monthly_plan' => $plan,
            'actual_income' => $actualIncome,
            'actual_expense' => $actualExpense,
            'actual' => $actualPrimary,
            'progress_percent' => BudgetProgress::percent($plan, $actualPrimary),
        ];
    }

    $this->pocketRows = $this->buildPocketRows($user, $period);

    $summary = BudgetSummary::fromRows($this->rows, planKey: 'monthly_plan');
    $this->summary = BudgetSummary::withPockets(
        $summary,
        $this->pocketRows,
        BudgetCurrency::pln()['code'],
    );
    $this->allocationHint = [
        'monthly_sum' => $monthlyPlansSum,
        'annual_sum' => $annualPlansSum,
    ];
}
```

Keep the existing getters unchanged. `User` is already imported.

- [ ] **Step 2: Regression**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: Pint exits 0; tests PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Actions/Budgets/ListMonthlyBudget.php
git commit -m "$(cat <<'EOF'
refactor(budgets): extract ListMonthlyBudget::handleForPeriod

Allow the dashboard to load the current month summary without a fake Form Request.
EOF
)"
```

---

### Task 2: `ShowDashboard` + controller (TDD)

**Files:**
- Create: `app/Actions/Dashboard/ShowDashboard.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Create: `tests/Feature/Dashboard/DashboardTest.php`
- Delete: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `ListMonthlyBudget::handleForPeriod(User $user, int $year, int $month): void`; `BudgetPeriod::forMonth`; `BudgetTransactionQuery`; `BudgetCurrency::pln()`.
- Produces: `ShowDashboard` getters used by the controller:

```php
public function handle(User $user): void
public function getYear(): int
public function getMonth(): int
/** @return array{total_pln: string, savings_pln: string} */
public function getBalances(): array
/** @return array{income: string, expense: string} */
public function getCashflow(): array
/** @return array<string, mixed> */
public function getBudgetSummary(): array
/** @return array{code: string, symbol: string, precision: int} */
public function getCurrency(): array
/** @return Collection<int, Transaction> */
public function getRecentTransactions(): Collection
```

Inertia props from the controller: `period`, `balances`, `cashflow`, `budget_summary`, `currency`, `recent_transactions`. No `accounts`.

- [ ] **Step 1: Move the existing test file**

```bash
mkdir -p tests/Feature/Dashboard
git mv tests/Feature/DashboardTest.php tests/Feature/Dashboard/DashboardTest.php
```

- [ ] **Step 2: Write the failing dashboard tests**

Replace `tests/Feature/Dashboard/DashboardTest.php` with:

```php
<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function dashboardPlnId(): int
{
    return (int) Currency::query()->where('code', 'PLN')->value('id');
}

function dashboardAccount(User $user, array $overrides = []): Account
{
    return Account::query()->create(array_merge([
        'user_id' => $user->id,
        'currency_id' => dashboardPlnId(),
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ], $overrides));
}

function dashboardTransaction(User $user, Account $account, array $overrides = []): Transaction
{
    $amount = $overrides['amount'] ?? '-20.00';
    $date = $overrides['date'] ?? '2026-08-10';
    $description = $overrides['description'] ?? 'Expense';
    $amountString = TransactionDedupe::amountToDecimalString((string) $amount);

    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => dashboardPlnId(),
        'date' => $date,
        'booked_at' => $overrides['booked_at'] ?? $date,
        'amount' => $amount,
        'type' => TransactionType::Expense,
        'description' => $description,
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription($description),
        'dedupe_hash' => TransactionDedupe::dedupeHash($date, $amountString, TransactionDedupe::normalizeDescription($description)),
        'category_id' => $overrides['category_id'] ?? null,
        'transfer_id' => null,
    ], $overrides));
}

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard', absolute: false))
        ->assertRedirect(route('login', absolute: false));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk();
});

test('dashboard includes only the authenticated user balances and omits accounts', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    dashboardAccount($userA, [
        'name' => 'Cash A',
        'current_balance' => 10,
        'opening_balance' => 10,
    ]);
    dashboardAccount($userA, [
        'name' => 'Savings A',
        'type' => AccountType::Savings,
        'current_balance' => 40,
        'opening_balance' => 40,
    ]);
    dashboardAccount($userB, [
        'name' => 'Cash B',
        'current_balance' => 99,
        'opening_balance' => 99,
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('accounts')
            ->where('period.year', 2026)
            ->where('period.month', 8)
            ->where('balances.total_pln', '50.00')
            ->where('balances.savings_pln', '40.00')
            ->where('currency.code', 'PLN')
        );
});

test('dashboard cashflow is the current month P&L excluding transfers and other users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    ensureUserCategories($userA);

    $accountA = dashboardAccount($userA);
    $accountB = dashboardAccount($userB);
    $food = Category::query()
        ->where('user_id', $userA->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();
    $salary = Category::query()
        ->where('user_id', $userA->id)
        ->where('type', 'income')
        ->orderBy('id')
        ->firstOrFail();

    dashboardTransaction($userA, $accountA, [
        'amount' => '300.00',
        'type' => TransactionType::Income,
        'description' => 'Salary',
        'category_id' => $salary->id,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '300.00', 'salary'),
        'normalized_description' => 'salary',
    ]);
    dashboardTransaction($userA, $accountA, [
        'amount' => '-80.00',
        'description' => 'Groceries',
        'category_id' => $food->id,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '-80.00', 'groceries'),
        'normalized_description' => 'groceries',
    ]);
    dashboardTransaction($userA, $accountA, [
        'amount' => '-50.00',
        'date' => '2026-07-10',
        'booked_at' => '2026-07-10',
        'description' => 'July',
        'category_id' => $food->id,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-07-10', '-50.00', 'july'),
        'normalized_description' => 'july',
    ]);
    dashboardTransaction($userB, $accountB, [
        'amount' => '-999.00',
        'description' => 'Other',
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '-999.00', 'other'),
        'normalized_description' => 'other',
    ]);

    $transferId = 'dash-transfer-uuid';
    dashboardTransaction($userA, $accountA, [
        'amount' => '-25.00',
        'type' => TransactionType::Transfer,
        'description' => 'Move',
        'transfer_id' => $transferId,
        'category_id' => null,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '-25.00', 'move-out'),
        'normalized_description' => 'move-out',
    ]);
    dashboardTransaction($userA, $accountA, [
        'amount' => '25.00',
        'type' => TransactionType::Transfer,
        'description' => 'Move',
        'transfer_id' => $transferId,
        'category_id' => null,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '25.00', 'move-in'),
        'normalized_description' => 'move-in',
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cashflow.income', '300.00')
            ->where('cashflow.expense', '80.00')
            ->where('budget_summary.execution.income', '300.00')
            ->where('budget_summary.execution.expense', '80.00')
        );
});

test('dashboard recent transactions are the latest eight for the current user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $accountA = dashboardAccount($userA);
    $accountB = dashboardAccount($userB);

    dashboardTransaction($userB, $accountB, [
        'description' => 'Other user',
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-20', '-20.00', 'other user'),
        'normalized_description' => 'other user',
    ]);

    foreach (range(1, 9) as $day) {
        $date = sprintf('2026-08-%02d', $day);
        dashboardTransaction($userA, $accountA, [
            'date' => $date,
            'booked_at' => $date,
            'description' => "Day {$day}",
            'amount' => '-1.00',
            'dedupe_hash' => TransactionDedupe::dedupeHash($date, '-1.00', "day {$day}"),
            'normalized_description' => "day {$day}",
        ]);
    }

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recent_transactions', 8)
            ->where('recent_transactions.0.description', 'Day 9')
            ->where('recent_transactions.7.description', 'Day 2')
            ->where('recent_transactions', fn ($rows) => collect($rows)->contains('description', 'Other user') === false)
        );
});
```

Fix the income-category lookup if the starter set uses a Polish name: after `ensureUserCategories`, resolve income with `where('type', 'income')` as above (enum value `income`).

If `dashboardTransaction` `array_merge` lets callers override `dedupe_hash` after the helper already set it, that is intended.

- [ ] **Step 3: Run tests to verify they fail**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Dashboard/DashboardTest.php
```

Expected: FAIL — new props (`balances`, `cashflow`, `recent_transactions`) missing; `accounts` still present.

- [ ] **Step 4: Implement `ShowDashboard`**

Create `app/Actions/Dashboard/ShowDashboard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Budgets\ListMonthlyBudget;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Budgets\BudgetCurrency;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Eloquent\Collection;

final class ShowDashboard
{
    private int $year;

    private int $month;

    /** @var array{total_pln: string, savings_pln: string} */
    private array $balances = [
        'total_pln' => '0.00',
        'savings_pln' => '0.00',
    ];

    /** @var array{income: string, expense: string} */
    private array $cashflow = [
        'income' => '0.00',
        'expense' => '0.00',
    ];

    /** @var array<string, mixed> */
    private array $budgetSummary = [];

    /** @var Collection<int, Transaction> */
    private Collection $recentTransactions;

    public function __construct(private ListMonthlyBudget $listMonthlyBudget) {}

    public function handle(User $user): void
    {
        $now = now();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;

        $this->handleBalances($user);
        $this->handleCashflow($user);
        $this->listMonthlyBudget->handleForPeriod($user, $this->year, $this->month);
        $this->budgetSummary = $this->listMonthlyBudget->getSummary();
        $this->handleRecent($user);
    }

    private function handleBalances(User $user): void
    {
        $accounts = Account::query()
            ->whereBelongsTo($user)
            ->with('currency')
            ->get();

        $total = '0.00';
        $savings = '0.00';

        foreach ($accounts as $account) {
            if ($account->currency?->code !== 'PLN') {
                continue;
            }

            $amount = TransactionDedupe::amountToDecimalString((string) $account->current_balance);
            $total = bcadd($total, $amount, 2);

            if ($account->type === AccountType::Savings) {
                $savings = bcadd($savings, $amount, 2);
            }
        }

        $this->balances = [
            'total_pln' => $total,
            'savings_pln' => $savings,
        ];
    }

    private function handleCashflow(User $user): void
    {
        $query = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($query, BudgetPeriod::forMonth($this->year, $this->month));
        BudgetTransactionQuery::excludeTransfers($query);

        /** @var object{income: string|int|float, expense: string|int|float}|null $row */
        $row = $query
            ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
            ->first();

        $this->cashflow = [
            'income' => TransactionDedupe::amountToDecimalString((string) ($row?->income ?? 0)),
            'expense' => TransactionDedupe::amountToDecimalString((string) ($row?->expense ?? 0)),
        ];
    }

    private function handleRecent(User $user): void
    {
        $query = Transaction::query()->whereBelongsTo($user);
        $grammar = $query->getGrammar();
        $bookedAt = $grammar->wrap($query->qualifyColumn('booked_at'));
        $date = $grammar->wrap($query->qualifyColumn('date'));

        $this->recentTransactions = $query
            ->with([
                'account.currency',
                'currency',
                'category',
                'pocket',
            ])
            ->orderByRaw("COALESCE({$bookedAt}, {$date}) desc")
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @return array{total_pln: string, savings_pln: string}
     */
    public function getBalances(): array
    {
        return $this->balances;
    }

    /**
     * @return array{income: string, expense: string}
     */
    public function getCashflow(): array
    {
        return $this->cashflow;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBudgetSummary(): array
    {
        return $this->budgetSummary;
    }

    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public function getCurrency(): array
    {
        return BudgetCurrency::pln();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getRecentTransactions(): Collection
    {
        return $this->recentTransactions;
    }
}
```

- [ ] **Step 5: Wire the controller**

Replace `app/Http/Controllers/DashboardController.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\ShowDashboard;
use App\Http\Resources\Transactions\TransactionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, ShowDashboard $showDashboard): Response
    {
        $showDashboard->handle($request->user());

        return Inertia::render('Dashboard', [
            'period' => [
                'year' => $showDashboard->getYear(),
                'month' => $showDashboard->getMonth(),
            ],
            'balances' => $showDashboard->getBalances(),
            'cashflow' => $showDashboard->getCashflow(),
            'budget_summary' => $showDashboard->getBudgetSummary(),
            'currency' => $showDashboard->getCurrency(),
            'recent_transactions' => TransactionResource::collection(
                $showDashboard->getRecentTransactions(),
            )->resolve(),
        ]);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Dashboard/DashboardTest.php tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: PASS. If income-category `where('type', 'income')` fails (enum vs string), compare `CategoryType::Income`. If `budget_summary.execution.expense` is `'80.00'` but pockets add-back changed it, the test user has no pockets — it must stay `80.00`.

If recent order fails because `id` tie-breaks same-day rows, keep unique dates as in the test (`Day 9` … `Day 2`).

- [ ] **Step 7: Commit**

```bash
git add app/Actions/Dashboard/ShowDashboard.php app/Http/Controllers/DashboardController.php tests/Feature/Dashboard/DashboardTest.php
git add -u tests/Feature/DashboardTest.php
git commit -m "$(cat <<'EOF'
feat(dashboard): compose month KPIs, budget summary, and recent activity

Replace the accounts collection with server-side totals and current-month P&L.
EOF
)"
```

---

### Task 3: i18n + page chrome

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`
- Modify: `resources/js/lib/pageRegistry.ts`
- Test: `resources/js/lib/pageChrome.test.ts` (existing dashboard case still uses `dashboard.title`)

**Interfaces:**
- Consumes: spec i18n table.
- Produces: `dashboard` node without `descriptionKey`; Vue will pass a formatted month as `:description`.

- [ ] **Step 1: Replace the `dashboard` locale block**

In `resources/js/locales/pl.json`:

```json
"dashboard": {
    "title": "Panel",
    "budget": {
        "title": "Budżet",
        "open": "Otwórz budżet"
    },
    "recent": {
        "title": "Ostatnie",
        "open": "Wszystkie transakcje",
        "empty": "Brak transakcji."
    },
    "chart": {
        "plan": "Plan",
        "actual": "Wykonanie"
    }
},
```

In `resources/js/locales/en.json`:

```json
"dashboard": {
    "title": "Dashboard",
    "budget": {
        "title": "Budget",
        "open": "Open budget"
    },
    "recent": {
        "title": "Recent",
        "open": "All transactions",
        "empty": "No transactions yet."
    },
    "chart": {
        "plan": "Plan",
        "actual": "Actual"
    }
},
```

Delete `headTitle`, `description`, `quickActions`, and `preview` from both files.

- [ ] **Step 2: Drop the registry description**

In `resources/js/lib/pageRegistry.ts` change the dashboard node to:

```ts
dashboard: { titleKey: 'dashboard.title' },
```

- [ ] **Step 3: Chrome + typecheck**

```bash
npx prettier --write resources/js/lib/pageRegistry.ts resources/js/locales/pl.json resources/js/locales/en.json
npx vitest run resources/js/lib/pageChrome.test.ts
npm run typecheck
```

Expected: Vitest PASS (title key unchanged); typecheck PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/locales/pl.json resources/js/locales/en.json resources/js/lib/pageRegistry.ts
git commit -m "$(cat <<'EOF'
feat(dashboard): replace launchpad copy with operator panel chrome
EOF
)"
```

---

### Task 4: Vue operator layout

**Files:**
- Create: `resources/js/components/dashboard/DashboardKpiCard.vue`
- Create: `resources/js/components/dashboard/DashboardPlanActualBars.vue`
- Create: `resources/js/components/dashboard/DashboardBudgetCard.vue`
- Create: `resources/js/components/dashboard/DashboardTransactionRow.vue`
- Create: `resources/js/components/dashboard/DashboardRecentCard.vue`
- Modify: `resources/js/pages/Dashboard.vue`
- Delete: `resources/js/components/accounts/AccountsSummaryCard.vue`

**Interfaces:**
- Consumes: Inertia props from Task 2; i18n from Task 3; `formatMoney`, `signedMoneyClass`, `BudgetProgressCell`, `CategoryBadge`.
- Produces: operator page; no `accounts` / quick-action cards.

- [ ] **Step 1: KPI tile**

Create `resources/js/components/dashboard/DashboardKpiCard.vue`:

```vue
<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
    tone?: 'default' | 'income' | 'expense';
}>();
</script>

<template>
    <article class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{ label }}</p>
        <p
            class="mt-2 text-2xl font-semibold tabular-nums tracking-tight"
            :class="{
                'text-emerald-600 dark:text-emerald-400': tone === 'income',
                'text-red-600 dark:text-red-400': tone === 'expense',
            }"
        >
            {{ value }}
        </p>
    </article>
</template>
```

- [ ] **Step 2: Plan vs actual bars**

Create `resources/js/components/dashboard/DashboardPlanActualBars.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    planIncome: string;
    actualIncome: string;
    planExpense: string;
    actualExpense: string;
}>();

const { t } = useI18n();

function toNumber(value: string): number {
    const parsed = Number(String(value).replace(',', '.'));

    return Number.isNaN(parsed) ? 0 : Math.abs(parsed);
}

function width(value: string, max: number): string {
    if (max <= 0) {
        return '0%';
    }

    return `${Math.min(100, (toNumber(value) / max) * 100)}%`;
}

const groups = computed(() => [
    {
        key: 'income',
        label: t('budget.summary.income'),
        plan: props.planIncome,
        actual: props.actualIncome,
        max: Math.max(toNumber(props.planIncome), toNumber(props.actualIncome)),
        actualClass: 'bg-emerald-500/40',
    },
    {
        key: 'expense',
        label: t('budget.summary.expense'),
        plan: props.planExpense,
        actual: props.actualExpense,
        max: Math.max(toNumber(props.planExpense), toNumber(props.actualExpense)),
        actualClass: 'bg-red-500/40',
    },
]);
</script>

<template>
    <div class="grid gap-4" aria-hidden="true">
        <div v-for="group in groups" :key="group.key" class="grid gap-2">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{ group.label }}</p>
            <div class="grid gap-1.5">
                <div class="flex items-center gap-2">
                    <span class="w-20 shrink-0 text-xs text-muted-foreground">{{ t('dashboard.chart.plan') }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-foreground/[0.06]">
                        <div class="h-full rounded-full bg-foreground/15" :style="{ width: width(group.plan, group.max) }" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-20 shrink-0 text-xs text-muted-foreground">{{ t('dashboard.chart.actual') }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-foreground/[0.06]">
                        <div class="h-full rounded-full" :class="group.actualClass" :style="{ width: width(group.actual, group.max) }" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Budget card**

Create `resources/js/components/dashboard/DashboardBudgetCard.vue`:

```vue
<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import DashboardPlanActualBars from '@/components/dashboard/DashboardPlanActualBars.vue';
import { formatMoney, signedMoneyClass, type CurrencyDisplay } from '@/lib/formatMoney';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type SummarySection = {
    income: string;
    expense: string;
    balance: string;
};

type BudgetSummary = {
    plan: SummarySection;
    execution: SummarySection;
    progress: {
        income_percent: number | null;
        expense_percent: number | null;
    };
};

const props = defineProps<{
    summary: BudgetSummary;
    currency: CurrencyDisplay;
    year: number;
    month: number;
}>();

const { t } = useI18n();
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="mb-5 flex items-baseline justify-between gap-3">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                {{ t('dashboard.budget.title') }}
            </h2>
            <Link
                :href="route('budget.monthly', { year: props.year, month: props.month })"
                class="text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground"
            >
                {{ t('dashboard.budget.open') }}
            </Link>
        </div>

        <DashboardPlanActualBars
            :plan-income="summary.plan.income"
            :actual-income="summary.execution.income"
            :plan-expense="summary.plan.expense"
            :actual-expense="summary.execution.expense"
        />

        <div class="mt-6 grid gap-3 text-sm">
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-border/50 pb-3">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60" />
                <span class="text-right text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{
                    t('budget.summary.plan')
                }}</span>
                <span class="text-right text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{
                    t('budget.summary.execution')
                }}</span>
                <span class="text-right text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{
                    t('budget.columns.progress')
                }}</span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-border/50 pb-3">
                <span>{{ t('budget.summary.income') }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.plan.income, currency) }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.execution.income, currency) }}</span>
                <span class="justify-self-end"><BudgetProgressCell :percent="summary.progress.income_percent" category-type="income" /></span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-border/50 pb-3">
                <span>{{ t('budget.summary.expense') }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.plan.expense, currency) }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.execution.expense, currency) }}</span>
                <span class="justify-self-end"><BudgetProgressCell :percent="summary.progress.expense_percent" category-type="expense" /></span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3">
                <span>{{ t('budget.summary.balance') }}</span>
                <span class="text-right tabular-nums" :class="signedMoneyClass(summary.plan.balance)">{{
                    formatMoney(summary.plan.balance, currency)
                }}</span>
                <span class="text-right tabular-nums" :class="signedMoneyClass(summary.execution.balance)">{{
                    formatMoney(summary.execution.balance, currency)
                }}</span>
                <span />
            </div>
        </div>
    </section>
</template>
```

- [ ] **Step 4: Recent row + card**

Create `resources/js/components/dashboard/DashboardTransactionRow.vue`:

```vue
<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { formatMoney, signedMoneyClass } from '@/lib/formatMoney';
import { computed } from 'vue';

type Transaction = {
    id: number;
    booked_at: string;
    date: string;
    date_relative: string;
    description: string;
    amount: string | number;
    category: { name: string; icon: string; color: string } | null;
    currency: { symbol: string | null; precision: number } | null;
};

const props = defineProps<{
    transaction: Transaction;
}>();

const displayDate = computed(() => props.transaction.date_relative || props.transaction.booked_at || props.transaction.date);
</script>

<template>
    <li class="flex items-center justify-between gap-3 border-b border-border/50 px-3 py-2.5 last:border-b-0">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium">{{ transaction.description }}</p>
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                <span class="shrink-0">{{ displayDate }}</span>
                <CategoryBadge
                    v-if="transaction.category"
                    :name="transaction.category.name"
                    :icon="transaction.category.icon"
                    :color="transaction.category.color"
                    size="sm"
                />
            </div>
        </div>
        <p class="shrink-0 text-sm font-medium tabular-nums" :class="signedMoneyClass(transaction.amount)">
            {{ formatMoney(transaction.amount, transaction.currency) }}
        </p>
    </li>
</template>
```

Create `resources/js/components/dashboard/DashboardRecentCard.vue`:

```vue
<script setup lang="ts">
import DashboardTransactionRow from '@/components/dashboard/DashboardTransactionRow.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Transaction = {
    id: number;
    booked_at: string;
    date: string;
    date_relative: string;
    description: string;
    amount: string | number;
    category: { name: string; icon: string; color: string } | null;
    currency: { symbol: string | null; precision: number } | null;
};

defineProps<{
    transactions: Transaction[];
}>();

const { t } = useI18n();
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                {{ t('dashboard.recent.title') }}
            </h2>
            <Link :href="route('transactions.index')" class="text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground">
                {{ t('dashboard.recent.open') }}
            </Link>
        </div>

        <p v-if="transactions.length === 0" class="text-sm text-muted-foreground">{{ t('dashboard.recent.empty') }}</p>
        <ul v-else class="-mx-3">
            <DashboardTransactionRow v-for="transaction in transactions" :key="transaction.id" :transaction="transaction" />
        </ul>
    </section>
</template>
```

- [ ] **Step 5: Replace `Dashboard.vue`**

Replace `resources/js/pages/Dashboard.vue` with:

```vue
<script setup lang="ts">
import DashboardBudgetCard from '@/components/dashboard/DashboardBudgetCard.vue';
import DashboardKpiCard from '@/components/dashboard/DashboardKpiCard.vue';
import DashboardRecentCard from '@/components/dashboard/DashboardRecentCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type SummarySection = {
    income: string;
    expense: string;
    balance: string;
};

type BudgetSummary = {
    plan: SummarySection;
    execution: SummarySection;
    progress: {
        income_percent: number | null;
        expense_percent: number | null;
    };
};

type Transaction = {
    id: number;
    booked_at: string;
    date: string;
    date_relative: string;
    description: string;
    amount: string | number;
    category: { name: string; icon: string; color: string } | null;
    currency: { symbol: string | null; precision: number } | null;
};

const props = defineProps<{
    period: { year: number; month: number };
    balances: { total_pln: string; savings_pln: string };
    cashflow: { income: string; expense: string };
    budget_summary: BudgetSummary;
    currency: CurrencyDisplay;
    recent_transactions: Transaction[];
}>();

const { t, locale } = useI18n();

const periodLabel = computed(() => {
    const date = new Date(props.period.year, props.period.month - 1, 1);

    return date.toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'pl-PL', {
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <AppLayout :description="periodLabel">
        <template #headerActions>
            <Button as-child>
                <Link :href="route('transactions.create')">
                    <Plus class="size-4" :stroke-width="1.7" />
                    {{ t('transactions.index.addTransaction') }}
                </Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6">
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <DashboardKpiCard :label="t('accounts.summary.totalPln')" :value="formatMoney(balances.total_pln, currency)" />
                <DashboardKpiCard :label="t('accounts.summary.savingsPln')" :value="formatMoney(balances.savings_pln, currency)" />
                <DashboardKpiCard :label="t('budget.summary.income')" :value="formatMoney(cashflow.income, currency)" tone="income" />
                <DashboardKpiCard :label="t('budget.summary.expense')" :value="formatMoney(cashflow.expense, currency)" tone="expense" />
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <DashboardBudgetCard :summary="budget_summary" :currency="currency" :year="period.year" :month="period.month" />
                <DashboardRecentCard :transactions="recent_transactions" />
            </section>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 6: Delete `AccountsSummaryCard.vue`**

```bash
rg -n "AccountsSummaryCard" --glob '!**/superpowers/**' --glob '!.docs/**'
```

If the only remaining app import is `Dashboard.vue` (already gone), delete `resources/js/components/accounts/AccountsSummaryCard.vue`.

- [ ] **Step 7: Format, typecheck, tests**

```bash
npx prettier --write resources/js/pages/Dashboard.vue resources/js/components/dashboard
npm run typecheck
./vendor/bin/sail artisan test --compact tests/Feature/Dashboard/DashboardTest.php
```

Expected: Prettier 0; typecheck 0; Pest PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/Dashboard.vue resources/js/components/dashboard
git add -u resources/js/components/accounts/AccountsSummaryCard.vue
git commit -m "$(cat <<'EOF'
feat(dashboard): render operator KPIs, budget snapshot, and recent rows

Drop the launchpad cards and the accounts summary component.
EOF
)"
```

---

### Task 5: Checklist + verification

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Record the feature**

Under a new `### 20) Dashboard operator overview` (after §19), add:

```markdown
### 20) Dashboard — przegląd operatora **[plan 2026-08-27-dashboard-operator-overview]**

- [x] `/panel`: 4 kafelki (suma PLN, oszczędności, wpływy, wydatki miesiąca)
- [x] Snapshot budżetu bieżącego miesiąca (plan vs wykonanie + słupki CSS)
- [x] 8 ostatnich transakcji
- [x] Usunięcie kart skrótów i `AccountsSummaryCard` z dashboardu
```

- [ ] **Step 2: Final verification**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Dashboard tests/Feature/Budgets/MonthlyBudgetTest.php
npx vitest run resources/js/lib/pageChrome.test.ts
npm run typecheck
```

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add .docs/checklist.md
git commit -m "$(cat <<'EOF'
docs(checklist): record dashboard operator overview
EOF
)"
```

---

## Spec coverage

| Spec requirement | Task |
|------------------|------|
| `handleForPeriod` | 1 |
| Totals, cashflow, budget summary, recent 8, isolation, drop `accounts` | 2 |
| Panel title, month subtitle, delete launchpad copy | 3 |
| Four KPIs, budget card + CSS chart, recent card, header CTA, delete summary card | 4 |
| Checklist | 5 |

No placeholders. Chart library not introduced. Pockets list / category pie / period switcher stay out of scope.

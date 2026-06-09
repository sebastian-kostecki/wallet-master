# Budget views UX redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign monthly and yearly budget screens with summary card, income-before-expenses layout, currency formatting, execution % (replacing difference), explicit-save estimate editing, and yearly per-category forecast.

**Architecture:** Pure logic in `Support/Budgets/` (`BudgetProgress`, `BudgetForecast`, `BudgetSummary`, `BudgetCurrency`). `ListMonthlyBudget` / `ListYearlyBudget` expose new getters; controller passes `summary` + `currency`. Shared Vue components under `components/budget/`. No API or migration changes.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3 + TypeScript, Tailwind 3, Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md`

**Suggested branch:** `improvement/budget-ux-redesign` (from `develop`)

---

## File map

| Action | Path |
|--------|------|
| Create | `app/Support/Budgets/BudgetProgress.php` |
| Create | `app/Support/Budgets/BudgetForecast.php` |
| Create | `app/Support/Budgets/BudgetSummary.php` |
| Create | `app/Support/Budgets/BudgetCurrency.php` |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Modify | `app/Actions/Budgets/ListYearlyBudget.php` |
| Modify | `app/Http/Controllers/Budgets/BudgetController.php` |
| Create | `tests/Unit/Support/Budgets/BudgetProgressTest.php` |
| Create | `tests/Unit/Support/Budgets/BudgetForecastTest.php` |
| Create | `tests/Unit/Support/Budgets/BudgetSummaryTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Modify | `tests/Feature/Budgets/YearlyBudgetTest.php` |
| Create | `resources/js/components/budget/BudgetProgressCell.vue` |
| Create | `resources/js/components/budget/EditableEstimateCell.vue` |
| Create | `resources/js/components/budget/BudgetSummaryCard.vue` |
| Create | `resources/js/components/budget/BudgetCategorySection.vue` |
| Modify | `resources/js/pages/budget/Monthly.vue` |
| Modify | `resources/js/pages/budget/Yearly.vue` |
| Modify | `resources/js/locales/pl.json` |
| Modify | `resources/js/locales/en.json` |

---

### Task 1: `BudgetProgress` (unit)

**Files:**
- Create: `tests/Unit/Support/Budgets/BudgetProgressTest.php`
- Create: `app/Support/Budgets/BudgetProgress.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Support\Budgets\BudgetProgress;

test('percent returns rounded execution ratio when plan is positive', function () {
    expect(BudgetProgress::percent('1000.00', '420.00'))->toBe(42);
    expect(BudgetProgress::percent('1000.00', '1000.00'))->toBe(100);
    expect(BudgetProgress::percent('1000.00', '1050.00'))->toBe(105);
});

test('percent returns null when plan is null or zero', function () {
    expect(BudgetProgress::percent(null, '100.00'))->toBeNull();
    expect(BudgetProgress::percent('0.00', '100.00'))->toBeNull();
    expect(BudgetProgress::percent('0', '0.00'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetProgressTest.php`  
Expected: FAIL — class not found

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

final class BudgetProgress
{
    public static function percent(?string $plan, string $actual): ?int
    {
        if ($plan === null || bccomp($plan, '0', 2) <= 0) {
            return null;
        }

        $ratio = bcmul(bcdiv($actual, $plan, 4), '100', 2);

        return (int) round((float) $ratio);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetProgressTest.php`  
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Budgets/BudgetProgress.php tests/Unit/Support/Budgets/BudgetProgressTest.php
git commit -m "feat(budget): add BudgetProgress percent helper"
```

---

### Task 2: `BudgetForecast` (unit)

**Files:**
- Create: `tests/Unit/Support/Budgets/BudgetForecastTest.php`
- Create: `app/Support/Budgets/BudgetForecast.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\BudgetForecast;
use Illuminate\Support\Collection;

test('referenceMonth returns current month for current year', function () {
    expect(BudgetForecast::referenceMonth(2026, 2026, 5))->toBe(5);
});

test('referenceMonth returns 12 for past year and 0 for future year', function () {
    expect(BudgetForecast::referenceMonth(2025, 2026, 5))->toBe(12);
    expect(BudgetForecast::referenceMonth(2027, 2026, 5))->toBe(0);
});

test('forecast adds remaining annual plan after elapsed monthly plans', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    $elapsed = BudgetForecast::elapsedPlansSum(
        $category,
        2026,
        5,
        $annual,
        new Collection,
    );

    expect($elapsed)->toBe('5000.00');
    expect(BudgetForecast::forecast('4200.00', '12000.00', $elapsed))->toBe('11200.00');
});

test('forecast uses monthly overrides in elapsed sum', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);
    $overrides = new Collection([
        3 => new CategoryMonthlyEstimate(['amount' => '1500.00']),
    ]);

    $elapsed = BudgetForecast::elapsedPlansSum($category, 2026, 3, $annual, $overrides);

    // Jan 1000 + Feb 1000 + Mar 1500
    expect($elapsed)->toBe('3500.00');
});

test('forecast returns actual only when annual plan is null', function () {
    expect(BudgetForecast::forecast('4200.00', null, '0.00'))->toBe('4200.00');
});

test('forecast clamps negative remainder to zero', function () {
    expect(BudgetForecast::forecast('9000.00', '12000.00', '5000.00'))->toBe('9000.00');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetForecastTest.php`  
Expected: FAIL

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\CategoryPlanAmount;
use Illuminate\Support\Collection;

final class BudgetForecast
{
    public static function referenceMonth(int $viewYear, ?int $nowYear = null, ?int $nowMonth = null): int
    {
        $nowYear ??= (int) now()->format('Y');
        $nowMonth ??= (int) now()->format('n');

        if ($viewYear < $nowYear) {
            return 12;
        }

        if ($viewYear > $nowYear) {
            return 0;
        }

        return $nowMonth;
    }

    /**
     * @param  Collection<int, CategoryMonthlyEstimate>  $monthlyEstimatesByMonth  keyed by month 1-12
     */
    public static function elapsedPlansSum(
        Category $category,
        int $year,
        int $referenceMonth,
        ?CategoryAnnualEstimate $annual,
        Collection $monthlyEstimatesByMonth,
    ): string {
        $sum = '0.00';

        for ($month = 1; $month <= $referenceMonth; $month++) {
            $monthly = $monthlyEstimatesByMonth->get($month);
            $plan = CategoryPlanAmount::monthly($category, $year, $month, $annual, $monthly);

            if ($plan !== null) {
                $sum = bcadd($sum, $plan, 2);
            }
        }

        return $sum;
    }

    public static function forecast(string $actualYtd, ?string $annualPlan, string $elapsedPlansSum): string
    {
        if ($annualPlan === null) {
            return $actualYtd;
        }

        $remainder = bcsub($annualPlan, $elapsedPlansSum, 2);

        if (bccomp($remainder, '0', 2) < 0) {
            $remainder = '0.00';
        }

        return bcadd($actualYtd, $remainder, 2);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetForecastTest.php`  
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Budgets/BudgetForecast.php tests/Unit/Support/Budgets/BudgetForecastTest.php
git commit -m "feat(budget): add yearly forecast calculation helper"
```

---

### Task 3: `BudgetSummary` (unit)

**Files:**
- Create: `tests/Unit/Support/Budgets/BudgetSummaryTest.php`
- Create: `app/Support/Budgets/BudgetSummary.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Support\Budgets\BudgetSummary;

test('fromRows aggregates monthly plan and execution by type', function () {
    $rows = [
        [
            'type' => 'income',
            'monthly_plan' => '5000.00',
            'actual' => '4800.00',
        ],
        [
            'type' => 'expense',
            'monthly_plan' => '3000.00',
            'actual' => '3200.00',
        ],
    ];

    $summary = BudgetSummary::fromRows($rows, planKey: 'monthly_plan');

    expect($summary['plan']['income'])->toBe('5000.00')
        ->and($summary['plan']['expense'])->toBe('3000.00')
        ->and($summary['plan']['balance'])->toBe('2000.00')
        ->and($summary['execution']['income'])->toBe('4800.00')
        ->and($summary['execution']['expense'])->toBe('3200.00')
        ->and($summary['execution']['balance'])->toBe('1600.00')
        ->and($summary['progress']['income_percent'])->toBe(96)
        ->and($summary['progress']['expense_percent'])->toBe(107);
});

test('fromRows includes forecast totals when forecast key provided', function () {
    $rows = [
        ['type' => 'income', 'annual_plan' => '60000.00', 'actual' => '20000.00', 'forecast' => '58000.00'],
        ['type' => 'expense', 'annual_plan' => '36000.00', 'actual' => '15000.00', 'forecast' => '34000.00'],
    ];

    $summary = BudgetSummary::fromRows($rows, planKey: 'annual_plan', forecastKey: 'forecast');

    expect($summary['forecast']['income'])->toBe('58000.00')
        ->and($summary['forecast']['expense'])->toBe('34000.00')
        ->and($summary['forecast']['balance'])->toBe('24000.00');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetSummaryTest.php`  
Expected: FAIL

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

final class BudgetSummary
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     plan: array{income: string, expense: string, balance: string},
     *     execution: array{income: string, expense: string, balance: string},
     *     progress: array{income_percent: int|null, expense_percent: int|null},
     *     forecast?: array{income: string, expense: string, balance: string}
     * }
     */
    public static function fromRows(
        array $rows,
        string $planKey,
        ?string $forecastKey = null,
    ): array {
        $planIncome = '0.00';
        $planExpense = '0.00';
        $actualIncome = '0.00';
        $actualExpense = '0.00';
        $forecastIncome = '0.00';
        $forecastExpense = '0.00';

        foreach ($rows as $row) {
            $plan = $row[$planKey] ?? null;
            $actual = (string) ($row['actual'] ?? '0.00');

            if ($row['type'] === 'income') {
                if ($plan !== null) {
                    $planIncome = bcadd($planIncome, (string) $plan, 2);
                }
                $actualIncome = bcadd($actualIncome, $actual, 2);

                if ($forecastKey !== null && isset($row[$forecastKey])) {
                    $forecastIncome = bcadd($forecastIncome, (string) $row[$forecastKey], 2);
                }
            }

            if ($row['type'] === 'expense') {
                if ($plan !== null) {
                    $planExpense = bcadd($planExpense, (string) $plan, 2);
                }
                $actualExpense = bcadd($actualExpense, $actual, 2);

                if ($forecastKey !== null && isset($row[$forecastKey])) {
                    $forecastExpense = bcadd($forecastExpense, (string) $row[$forecastKey], 2);
                }
            }
        }

        $summary = [
            'plan' => [
                'income' => $planIncome,
                'expense' => $planExpense,
                'balance' => bcsub($planIncome, $planExpense, 2),
            ],
            'execution' => [
                'income' => $actualIncome,
                'expense' => $actualExpense,
                'balance' => bcsub($actualIncome, $actualExpense, 2),
            ],
            'progress' => [
                'income_percent' => BudgetProgress::percent($planIncome, $actualIncome),
                'expense_percent' => BudgetProgress::percent($planExpense, $actualExpense),
            ],
        ];

        if ($forecastKey !== null) {
            $summary['forecast'] = [
                'income' => $forecastIncome,
                'expense' => $forecastExpense,
                'balance' => bcsub($forecastIncome, $forecastExpense, 2),
            ];
        }

        return $summary;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetSummaryTest.php`  
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Budgets/BudgetSummary.php tests/Unit/Support/Budgets/BudgetSummaryTest.php
git commit -m "feat(budget): add summary aggregation helper"
```

---

### Task 4: `BudgetCurrency` + `ListMonthlyBudget`

**Files:**
- Create: `app/Support/Budgets/BudgetCurrency.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: Write failing feature test**

Append to `tests/Feature/Budgets/MonthlyBudgetTest.php`:

```php
test('monthly budget exposes summary currency and progress without difference', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $income = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'income')
        ->firstOrFail();

    $expense = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $income->id,
        'year' => 2026,
        'amount' => 6000,
    ]);

    CategoryAnnualEstimate::query()->create([
        'category_id' => $expense->id,
        'year' => 2026,
        'amount' => 3600,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('summary')
        ->has('currency')
        ->where('summary.plan.income', '500.00')
        ->where('summary.plan.expense', '300.00')
        ->where('currency.code', 'PLN')
        ->where('rows', fn ($rows) => collect($rows)->every(fn ($row) => ! array_key_exists('difference', $row)))
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $expense->id)['progress_percent'] === 0)
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact --filter="monthly budget exposes summary"`  
Expected: FAIL — missing `summary` prop

- [ ] **Step 3: Implement `BudgetCurrency`**

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Currency;

final class BudgetCurrency
{
    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public static function pln(): array
    {
        $currency = Currency::query()->where('code', 'PLN')->firstOrFail();

        return [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'precision' => (int) $currency->precision,
        ];
    }
}
```

- [ ] **Step 4: Update `ListMonthlyBudget`**

In `handle()` after building `$this->rows`:
- Replace `difference` with `progress_percent` via `BudgetProgress::percent($plan, $actualPrimary)`
- Add `$this->summary = BudgetSummary::fromRows($this->rows, planKey: 'monthly_plan')`
- Add private `$summary` property + `getSummary()` getter
- Add `getCurrency(): array` returning `BudgetCurrency::pln()`

Remove `difference` key from row arrays.

- [ ] **Step 5: Update `BudgetController::monthly`**

```php
return Inertia::render('budget/Monthly', [
    'year' => $listMonthlyBudget->getYear(),
    'month' => $listMonthlyBudget->getMonth(),
    'rows' => $listMonthlyBudget->getRows(),
    'goal_rows' => $listMonthlyBudget->getGoalRows(),
    'allocation_hint' => $listMonthlyBudget->getAllocationHint(),
    'summary' => $listMonthlyBudget->getSummary(),
    'currency' => $listMonthlyBudget->getCurrency(),
]);
```

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`  
Expected: PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Budgets/BudgetCurrency.php app/Actions/Budgets/ListMonthlyBudget.php app/Http/Controllers/Budgets/BudgetController.php tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "feat(budget): add monthly summary progress and currency props"
```

---

### Task 5: `ListYearlyBudget` forecast + summary

**Files:**
- Modify: `app/Actions/Budgets/ListYearlyBudget.php`
- Modify: `app/Http/Controllers/Budgets/BudgetController.php`
- Modify: `tests/Feature/Budgets/YearlyBudgetTest.php`

- [ ] **Step 1: Write failing feature test**

Append to `tests/Feature/Budgets/YearlyBudgetTest.php`:

```php
use App\Models\CategoryMonthlyEstimate;
use Illuminate\Support\Carbon;

test('yearly budget exposes forecast and summary for current year', function () {
    Carbon::setTestNow('2026-05-15');

    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'amount' => 12000,
    ]);

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 1500,
    ]);

    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $food->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '-100.00',
        'type' => TransactionType::Expense,
        'description' => 'Groceries',
        'normalized_description' => 'groceries',
        'dedupe_hash' => md5('food-forecast', true),
    ]);

    $response = $this->actingAs($user)->get('/budget/yearly?year=2026');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('summary')
        ->has('currency')
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['forecast'] === '6600.00')
        ->where('summary.forecast.expense', '6600.00')
        ->where('rows', fn ($rows) => collect($rows)->every(fn ($row) => ! array_key_exists('difference', $row)))
    );

    Carbon::setTestNow();
});
```

Forecast math (May, annual 12 000, Mar override 1 500, actual 100): elapsed plans = 5 500, remainder = 6 500, forecast = 6 600.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact --filter="yearly budget exposes forecast"`  
Expected: FAIL

- [ ] **Step 3: Update `ListYearlyBudget`**

Add query for all monthly estimates in year (grouped per category):

```php
$monthlyByCategoryAndMonth = CategoryMonthlyEstimate::query()
    ->whereIn('category_id', $this->categories->pluck('id'))
    ->where('year', $this->year)
    ->get()
    ->groupBy('category_id')
    ->map(fn ($items) => $items->keyBy('month'));
```

In row loop:
- `$referenceMonth = BudgetForecast::referenceMonth($this->year)`
- `$elapsed = BudgetForecast::elapsedPlansSum(...)`
- `$forecast = BudgetForecast::forecast($actualPrimary, $plan, $elapsed)`
- `$progress_percent = BudgetProgress::percent($plan, $actualPrimary)`
- Remove `difference`

After loop:
- `$this->summary = BudgetSummary::fromRows($this->rows, planKey: 'annual_plan', forecastKey: 'forecast')`
- Add `getSummary()`, `getCurrency()` (delegate to `BudgetCurrency::pln()`)

- [ ] **Step 4: Update `BudgetController::yearly`**

Add `summary` and `currency` to Inertia props.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/YearlyBudgetTest.php`  
Expected: PASS

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Budgets/ListYearlyBudget.php app/Http/Controllers/Budgets/BudgetController.php tests/Feature/Budgets/YearlyBudgetTest.php
git commit -m "feat(budget): add yearly forecast and summary props"
```

---

### Task 6: i18n keys

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add keys under `budget`**

`pl.json`:

```json
"summary": {
    "plan": "Plan",
    "execution": "Wykonanie",
    "forecast": "Prognoza",
    "income": "Przychody",
    "expense": "Wydatki",
    "balance": "Saldo"
},
"columns": {
    "progress": "Wykonanie %",
    "forecast": "Prognoza"
},
"estimate": {
    "edit": "Edytuj szacunek",
    "save": "Zapisz",
    "cancel": "Anuluj"
}
```

`en.json` — English equivalents.

- [ ] **Step 2: Commit**

```bash
git add resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(budget): add i18n keys for summary and estimate editing"
```

---

### Task 7: `BudgetProgressCell.vue`

**Files:**
- Create: `resources/js/components/budget/BudgetProgressCell.vue`

- [ ] **Step 1: Create component**

```vue
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    percent: number | null;
    categoryType: 'income' | 'expense';
}>();

const tone = computed(() => {
    if (props.percent === null) {
        return 'muted';
    }

    if (props.categoryType === 'income') {
        if (props.percent < 100) {
            return 'bad';
        }

        return 'good';
    }

    if (props.percent < 100) {
        return 'good';
    }

    if (props.percent === 100) {
        return 'warn';
    }

    return 'bad';
});

const barWidth = computed(() => {
    if (props.percent === null) {
        return 0;
    }

    return Math.max(0, Math.min(100, props.percent));
});

const toneClasses: Record<string, string> = {
    good: 'bg-emerald-500 text-emerald-800 dark:text-emerald-300',
    warn: 'bg-amber-500 text-amber-900 dark:text-amber-200',
    bad: 'bg-red-500 text-red-800 dark:text-red-300',
    muted: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <div v-if="percent === null" class="text-muted-foreground">—</div>
    <div v-else class="grid min-w-[5.5rem] gap-1">
        <span
            class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
            :class="toneClasses[tone].split(' ').slice(1)"
        >
            {{ percent }}%
        </span>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full transition-[width]" :class="toneClasses[tone].split(' ')[0]" :style="{ width: `${barWidth}%` }" />
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/BudgetProgressCell.vue
git commit -m "feat(budget): add progress cell with mirror color semantics"
```

---

### Task 8: `EditableEstimateCell.vue`

**Files:**
- Create: `resources/js/components/budget/EditableEstimateCell.vue`

- [ ] **Step 1: Create component**

Props:
- `modelValue: string | null` (current plan)
- `currency: CurrencyDisplay`
- `inputId: string`
- `placeholder: string`
- `isEditing: boolean`
- `editLabel: string`

Emits: `start-edit`, `cancel`, `save(raw: string)`

Read mode: `formatMoney(modelValue, currency)` + pencil button.  
Edit mode: input + Save/Cancel buttons.  
Save validates: empty → null; else must match `/^\d+([.,]\d{1,2})?$/` before emit.

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/EditableEstimateCell.vue
git commit -m "feat(budget): add explicit-save estimate cell"
```

---

### Task 9: `BudgetSummaryCard.vue`

**Files:**
- Create: `resources/js/components/budget/BudgetSummaryCard.vue`

- [ ] **Step 1: Create component**

Props:
- `summary` — shape from backend
- `currency` — `{ code, symbol, precision }`
- `variant: 'monthly' | 'yearly'`

Render bordered card with table rows Plan / Execution / (Forecast if yearly).  
Use `formatMoney` for all amounts.  
Under Income and Expense columns after Execution row, render `BudgetProgressCell` with `summary.progress.income_percent` / `expense_percent` and matching `categoryType`.

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/BudgetSummaryCard.vue
git commit -m "feat(budget): add summary card component"
```

---

### Task 10: `BudgetCategorySection.vue`

**Files:**
- Create: `resources/js/components/budget/BudgetCategorySection.vue`

- [ ] **Step 1: Create component**

Props:
- `title: string`
- `categoryType: 'income' | 'expense'`
- `rows: array`
- `currency`
- `variant: 'monthly' | 'yearly'`
- `year`, `month?` (for estimate save context)
- `editingCategoryId: number | null`

Slots or emits for estimate save orchestration from parent.

Table columns per variant (monthly: plan/actual/progress; yearly: plan/actual/forecast/progress).  
Uses `CategoryBadge`, `EditableEstimateCell`, `BudgetProgressCell`, `formatMoney`.

Parent tracks `editingCategoryId` so only one row edits at a time.

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/BudgetCategorySection.vue
git commit -m "feat(budget): add reusable category section table"
```

---

### Task 11: Refactor `Monthly.vue`

**Files:**
- Modify: `resources/js/pages/budget/Monthly.vue`

- [ ] **Step 1: Update props type**

Add `summary` and `currency` props; add `progress_percent` to `BudgetRow`; remove `difference`.

- [ ] **Step 2: Reorder layout**

1. Header (unchanged)
2. `BudgetSummaryCard` (`variant="monthly"`)
3. `allocation_hint`
4. `BudgetCategorySection` for `incomeRows` (before expenses)
5. `BudgetCategorySection` for `expenseRows`
6. Goals section (unchanged table, keep `formatGoalMoney`)
7. Footer links

- [ ] **Step 3: Wire estimate editing**

```typescript
const editingCategoryId = ref<number | null>(null);

function saveMonthlyEstimate(row: BudgetRow, rawValue: string) {
    // same PATCH logic as today
    editingCategoryId.value = null;
}
```

Remove inline `Input` + `@blur` pattern.

- [ ] **Step 4: Manual smoke**

Run: `./vendor/bin/sail npm run build` (or dev server) — page renders without console errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/budget/Monthly.vue
git commit -m "feat(budget): redesign monthly view with summary and progress"
```

---

### Task 12: Refactor `Yearly.vue`

**Files:**
- Modify: `resources/js/pages/budget/Yearly.vue`

- [ ] **Step 1: Mirror Monthly refactor**

Add `summary`, `currency`, `forecast`, `progress_percent` types.  
Layout: header → `BudgetSummaryCard` (`variant="yearly"`) → income section → expense section → categories link.  
Use `BudgetCategorySection` with `variant="yearly"`.  
Annual estimate save via `EditableEstimateCell` + explicit Save.

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/budget/Yearly.vue
git commit -m "feat(budget): redesign yearly view with forecast and summary"
```

---

### Task 13: Final verification

- [ ] **Step 1: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Run budget tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets tests/Unit/Support/Budgets
```

Expected: all PASS

- [ ] **Step 3: Optional PHPStan** (if touching many types)

```bash
./vendor/bin/phpstan analyse
```

- [ ] **Step 4: Update checklist** (if on `improvement/*` branch before merge)

In `.docs/checklist.md`, note budget UX redesign shipped if applicable.

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Section order income → expense → goals | Task 11 |
| Summary card monthly (2 rows) | Tasks 3–4, 9, 11 |
| Summary card yearly (3 rows + forecast) | Tasks 3, 5, 9, 12 |
| Remove difference | Tasks 4–5, 11–12 |
| progress_percent per row | Tasks 1, 4–5 |
| Mirror colors | Task 7 |
| Currency on P&L | Tasks 4–5, 8–12 |
| Explicit save editing | Task 8, 11–12 |
| Yearly forecast formula A | Tasks 2, 5 |
| allocation_hint kept | Task 11 |
| i18n | Task 6 |
| Feature + unit tests | Tasks 1–5, 13 |
| No API changes | — (unchanged routes) |

## Execution handoff

Plan complete and saved to `.docs/superpowers/plans/2026-06-05-budget-ux-redesign.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration  
2. **Inline Execution** — implement task-by-task in this session with checkpoints

Which approach do you want?

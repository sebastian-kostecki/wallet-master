# Budget yearly dual-plan edit + independent monthly model — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users set annual + optional bulk monthly plans from the yearly budget view; show explicit monthly plans only (0 when unset); keep yearly forecast driven by annual with `annual ÷ 12` fallback for elapsed months.

**Architecture:** Split `CategoryPlanAmount` into display (`monthly`) vs forecast (`monthlyForForecast`) resolution. Add `SaveYearlyCategoryPlan` atomic write action. Extend `BudgetForecast` with `closedMonthForForecast` so forecast actual excludes the current calendar month. New `YearlyPlanEditCell.vue` for dual inline edit on yearly view.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, TypeScript, Tailwind CSS 3, Sail, Pint.

**Spec:** `.docs/superpowers/specs/2026-06-11-budget-yearly-dual-plan-edit-design.md`  
**Suggested branch:** `improvement/budget-yearly-dual-plan-edit` (from `develop`)

---

## File map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Support/Budgets/CategoryPlanAmount.php` | Display vs forecast monthly resolution |
| Create | `tests/Unit/Support/Budgets/CategoryPlanAmountTest.php` | Unit tests for both paths |
| Modify | `app/Support/Budgets/BudgetForecast.php` | `closedMonthForForecast`, forecast elapsed uses `monthlyForForecast` |
| Modify | `tests/Unit/Support/Budgets/BudgetForecastTest.php` | Updated expectations |
| Modify | `app/Support/Budgets/BudgetPeriod.php` | `throughMonth()` period helper |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` | Always-string monthly plan; sum always includes plan |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` | No override → `0.00` |
| Modify | `app/Actions/Budgets/ListYearlyBudget.php` | Forecast actual base + `monthly_template` |
| Create | `app/Support/Budgets/YearlyMonthlyTemplate.php` | Eligible months + template derivation |
| Create | `tests/Unit/Support/Budgets/YearlyMonthlyTemplateTest.php` | Template + eligible month tests |
| Create | `app/Actions/Categories/SaveYearlyCategoryPlan.php` | Atomic annual + bulk monthly save |
| Create | `app/Http/Requests/Categories/SaveYearlyCategoryPlanRequest.php` | Validation + authorize |
| Modify | `app/Http/Controllers/Categories/CategoryController.php` | `saveYearlyCategoryPlan` |
| Modify | `routes/categories.php` | New route |
| Modify | `config/routes.php` | PL + EN path segments |
| Modify | `app/Support/Routing/LegacyRouteRedirector.php` | Legacy redirect for new path |
| Modify | `tests/Feature/Categories/CategoryEstimatesTest.php` | Yearly-plan endpoint tests |
| Modify | `tests/Feature/Budgets/YearlyBudgetTest.php` | Forecast value + `monthly_template` prop |
| Create | `resources/js/components/budget/YearlyPlanEditCell.vue` | Dual-input edit cell |
| Modify | `resources/js/components/budget/BudgetCategorySection.vue` | Yearly variant wiring |
| Modify | `resources/js/pages/budget/Yearly.vue` | `saveYearlyPlan`, wider plan column |
| Modify | `resources/js/locales/pl.json` | Monthly plan placeholder/label |
| Modify | `resources/js/locales/en.json` | Monthly plan placeholder/label |
| Modify | `.docs/prd.md` | FR-C4 monthly default = 0 |

---

### Task 1: Split `CategoryPlanAmount` display vs forecast

**Files:**
- Modify: `app/Support/Budgets/CategoryPlanAmount.php`
- Create: `tests/Unit/Support/Budgets/CategoryPlanAmountTest.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/Support/Budgets/CategoryPlanAmountTest.php`:

```php
<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\CategoryPlanAmount;

test('monthly display returns override amount when set', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $monthly = new CategoryMonthlyEstimate(['amount' => '1500.00']);

    expect(CategoryPlanAmount::monthly($category, 2026, 3, null, $monthly))->toBe('1500.00');
});

test('monthly display returns zero when no override even with annual', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    expect(CategoryPlanAmount::monthly($category, 2026, 3, $annual, null))->toBe('0.00');
});

test('monthlyForForecast returns annual divided by twelve when no override', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    expect(CategoryPlanAmount::monthlyForForecast($category, 2026, 3, $annual, null))->toBe('1000.00');
});

test('monthlyForForecast prefers override over annual', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);
    $monthly = new CategoryMonthlyEstimate(['amount' => '1500.00']);

    expect(CategoryPlanAmount::monthlyForForecast($category, 2026, 3, $annual, $monthly))->toBe('1500.00');
});

test('monthlyForForecast returns null when no annual and no override', function () {
    $category = new Category(['type' => CategoryType::Expense]);

    expect(CategoryPlanAmount::monthlyForForecast($category, 2026, 3, null, null))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/CategoryPlanAmountTest.php
```

Expected: FAIL — `monthlyForForecast` not defined; `monthly` returns `1000.00` instead of `0.00`.

- [ ] **Step 3: Implement `CategoryPlanAmount`**

Replace `app/Support/Budgets/CategoryPlanAmount.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;

final class CategoryPlanAmount
{
    public static function monthly(
        Category $category,
        int $year,
        int $month,
        ?CategoryAnnualEstimate $annual,
        ?CategoryMonthlyEstimate $monthly,
    ): string {
        if ($monthly?->amount !== null) {
            return (string) $monthly->amount;
        }

        return '0.00';
    }

    public static function monthlyForForecast(
        Category $category,
        int $year,
        int $month,
        ?CategoryAnnualEstimate $annual,
        ?CategoryMonthlyEstimate $monthly,
    ): ?string {
        if ($monthly?->amount !== null) {
            return (string) $monthly->amount;
        }

        if ($annual?->amount !== null) {
            return bcdiv((string) $annual->amount, '12', 2);
        }

        return null;
    }

    public static function annual(?CategoryAnnualEstimate $annual): ?string
    {
        if ($annual?->amount === null) {
            return null;
        }

        return (string) $annual->amount;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/CategoryPlanAmountTest.php
```

Expected: PASS (5 tests).

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Support/Budgets/CategoryPlanAmount.php tests/Unit/Support/Budgets/CategoryPlanAmountTest.php
git commit -m "$(cat <<'EOF'
feat(budget): split monthly display and forecast plan resolution

Display uses explicit overrides only (0 default); forecast keeps annual÷12 fallback.
EOF
)"
```

---

### Task 2: Update `BudgetForecast` for closed months + forecast resolution

**Files:**
- Modify: `app/Support/Budgets/BudgetForecast.php`
- Modify: `tests/Unit/Support/Budgets/BudgetForecastTest.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Unit/Support/Budgets/BudgetForecastTest.php`:

```php
test('closedMonthForForecast excludes current month for current year', function () {
    expect(BudgetForecast::closedMonthForForecast(2026, 2026, 6))->toBe(5);
    expect(BudgetForecast::closedMonthForForecast(2026, 2026, 1))->toBe(0);
});

test('closedMonthForForecast returns twelve for past year and zero for future year', function () {
    expect(BudgetForecast::closedMonthForForecast(2025, 2026, 6))->toBe(12);
    expect(BudgetForecast::closedMonthForForecast(2027, 2026, 6))->toBe(0);
});
```

Update existing test `forecast adds remaining annual plan after elapsed monthly plans`:

```php
test('forecast adds remaining annual plan after elapsed monthly plans', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    $elapsed = BudgetForecast::elapsedPlansSum(
        $category,
        2026,
        4,
        $annual,
        new Collection,
    );

    expect($elapsed)->toBe('4000.00');
    expect(BudgetForecast::forecast('4200.00', '12000.00', $elapsed))->toBe('12200.00');
});
```

Update `forecast uses monthly overrides in elapsed sum` — use `closedMonth = 3`:

```php
    $elapsed = BudgetForecast::elapsedPlansSum($category, 2026, 3, $annual, $overrides);
    expect($elapsed)->toBe('3500.00');
```

- [ ] **Step 2: Run tests to verify failure**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetForecastTest.php
```

Expected: FAIL on `closedMonthForForecast` and updated elapsed sums.

- [ ] **Step 3: Implement `BudgetForecast` changes**

In `app/Support/Budgets/BudgetForecast.php`:

1. Add method:

```php
public static function closedMonthForForecast(int $viewYear, ?int $nowYear = null, ?int $nowMonth = null): int
{
    $nowYear ??= (int) now()->format('Y');
    $nowMonth ??= (int) now()->format('n');

    if ($viewYear < $nowYear) {
        return 12;
    }

    if ($viewYear > $nowYear) {
        return 0;
    }

    return max(0, $nowMonth - 1);
}
```

2. Rename parameter in `elapsedPlansSum` from `$referenceMonth` to `$throughMonth` (docblock: months 1..throughMonth inclusive).

3. Inside the loop, replace `CategoryPlanAmount::monthly` with `CategoryPlanAmount::monthlyForForecast`.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetForecastTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Budgets/BudgetForecast.php tests/Unit/Support/Budgets/BudgetForecastTest.php
git commit -m "$(cat <<'EOF'
feat(budget): forecast elapsed plans use closed months and annual÷12

EOF
)"
```

---

### Task 3: `BudgetPeriod::throughMonth` + monthly budget display

**Files:**
- Modify: `app/Support/Budgets/BudgetPeriod.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: Update failing feature test**

In `tests/Feature/Budgets/MonthlyBudgetTest.php`, rename and change first test:

```php
test('monthly budget shows zero plan when no monthly override', function () {
    // ... same setup with annual 12000 ...
    $response->assertInertia(fn ($page) => $page
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['monthly_plan'] === '0.00')
    );
});
```

- [ ] **Step 2: Run test to verify failure**

```bash
./vendor/bin/sail artisan test --compact --filter="monthly budget shows zero plan"
```

Expected: FAIL — `1000.00`.

- [ ] **Step 3: Add `BudgetPeriod::throughMonth`**

In `app/Support/Budgets/BudgetPeriod.php`:

```php
public static function throughMonth(int $year, int $month): self
{
    $start = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
    $end = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

    return new self($start, $end);
}
```

- [ ] **Step 4: Update `ListMonthlyBudget` plan sum**

In `app/Actions/Budgets/ListMonthlyBudget.php`, replace:

```php
if ($plan !== null) {
    $monthlyPlansSum = bcadd($monthlyPlansSum, $plan, 2);
}
```

with:

```php
$monthlyPlansSum = bcadd($monthlyPlansSum, $plan, 2);
```

(`monthly()` now always returns a string.)

- [ ] **Step 5: Run monthly budget tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Budgets/BudgetPeriod.php app/Actions/Budgets/ListMonthlyBudget.php tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "$(cat <<'EOF'
feat(budget): monthly view shows zero plan without explicit override

EOF
)"
```

---

### Task 4: `YearlyMonthlyTemplate` support class

**Files:**
- Create: `app/Support/Budgets/YearlyMonthlyTemplate.php`
- Create: `tests/Unit/Support/Budgets/YearlyMonthlyTemplateTest.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/Support/Budgets/YearlyMonthlyTemplateTest.php`:

```php
<?php

use App\Models\Category;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\YearlyMonthlyTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

test('eligibleMonths returns six through twelve for current year in June', function () {
    Carbon::setTestNow('2026-06-15');

    expect(YearlyMonthlyTemplate::eligibleMonths(2026))->toBe([6, 7, 8, 9, 10, 11, 12]);

    Carbon::setTestNow();
});

test('eligibleMonths returns empty for past year', function () {
    Carbon::setTestNow('2026-06-15');

    expect(YearlyMonthlyTemplate::eligibleMonths(2025))->toBe([]);

    Carbon::setTestNow();
});

test('forCategory returns uniform explicit amount on eligible months without override', function () {
    Carbon::setTestNow('2026-06-15');
    $category = Category::factory()->make(['id' => 1]);
    $byMonth = new Collection([
        6 => new CategoryMonthlyEstimate(['amount' => '400.00']),
        7 => new CategoryMonthlyEstimate(['amount' => '400.00']),
    ]);

    expect(YearlyMonthlyTemplate::forCategory($category, 2026, $byMonth))->toBe('400.00');

    Carbon::setTestNow();
});

test('forCategory returns null when eligible explicit amounts differ', function () {
    Carbon::setTestNow('2026-06-15');
    $category = Category::factory()->make(['id' => 1]);
    $byMonth = new Collection([
        6 => new CategoryMonthlyEstimate(['amount' => '400.00']),
        7 => new CategoryMonthlyEstimate(['amount' => '500.00']),
    ]);

    expect(YearlyMonthlyTemplate::forCategory($category, 2026, $byMonth))->toBeNull();

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/YearlyMonthlyTemplateTest.php
```

- [ ] **Step 3: Implement `YearlyMonthlyTemplate`**

Create `app/Support/Budgets/YearlyMonthlyTemplate.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Category;
use App\Models\CategoryMonthlyEstimate;
use Illuminate\Support\Collection;

final class YearlyMonthlyTemplate
{
    /** @return list<int> */
    public static function eligibleMonths(int $year): array
    {
        $nowYear = (int) now()->format('Y');
        $nowMonth = (int) now()->format('n');

        if ($year < $nowYear) {
            return [];
        }

        if ($year > $nowYear) {
            return range(1, 12);
        }

        return range($nowMonth, 12);
    }

    /**
     * @param  Collection<int, CategoryMonthlyEstimate>  $monthlyByMonth  keyed by month
     */
    public static function forCategory(Category $category, int $year, Collection $monthlyByMonth): ?string
    {
        $amounts = [];

        foreach (self::eligibleMonths($year) as $month) {
            $monthly = $monthlyByMonth->get($month);

            if ($monthly?->amount === null) {
                continue;
            }

            $amounts[] = (string) $monthly->amount;
        }

        if ($amounts === []) {
            return null;
        }

        $unique = array_unique($amounts);

        return count($unique) === 1 ? $unique[0] : null;
    }

    public static function hasOverride(int $month, ?CategoryMonthlyEstimate $monthly): bool
    {
        return $monthly !== null && $monthly->amount !== null;
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/YearlyMonthlyTemplateTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Support/Budgets/YearlyMonthlyTemplate.php tests/Unit/Support/Budgets/YearlyMonthlyTemplateTest.php
git commit -m "$(cat <<'EOF'
feat(budget): add YearlyMonthlyTemplate for bulk edit pre-fill

EOF
)"
```

---

### Task 5: Update `ListYearlyBudget` forecast + `monthly_template`

**Files:**
- Modify: `app/Actions/Budgets/ListYearlyBudget.php`
- Modify: `tests/Feature/Budgets/YearlyBudgetTest.php`

- [ ] **Step 1: Update forecast test expectation**

In `tests/Feature/Budgets/YearlyBudgetTest.php`, change forecast assertion in `yearly budget exposes forecast and summary for current year`:

```php
->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['forecast'] === '7600.00')
->where('summary.forecast.expense', '7600.00')
```

Add test for `monthly_template`:

```php
test('yearly budget exposes monthly_template on rows', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 400,
    ]);
    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 7,
        'amount' => 400,
    ]);

    $response = $this->actingAs($user)->get(route('budget.yearly', ['year' => 2026], absolute: false));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['monthly_template'] === '400.00')
    );

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/YearlyBudgetTest.php
```

- [ ] **Step 3: Update `ListYearlyBudget`**

Key changes in the category loop:

1. Keep existing full-year actual query for `actual` / execution / progress (unchanged).

2. Add forecast actual base query:

```php
$closedMonth = BudgetForecast::closedMonthForForecast($this->year);
$actualBasePrimary = '0.00';

if ($closedMonth > 0) {
    $forecastPeriod = BudgetPeriod::throughMonth($this->year, $closedMonth);
    $forecastActualsQuery = BudgetTransactionQuery::forUser($user);
    BudgetTransactionQuery::inPeriod($forecastActualsQuery, $forecastPeriod);
    BudgetTransactionQuery::excludeTransfers($forecastActualsQuery);
    $forecastActual = $forecastActualsQuery
        ->where('category_id', $category->id)
        ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
        ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
        ->first();
    $actualBasePrimary = $category->type === CategoryType::Income
        ? TransactionDedupe::amountToDecimalString((string) ($forecastActual->income ?? '0'))
        : TransactionDedupe::amountToDecimalString((string) ($forecastActual->expense ?? '0'));
}
```

3. Replace elapsed sum call:

```php
$elapsedPlansSum = BudgetForecast::elapsedPlansSum(
    $category,
    $this->year,
    $closedMonth,
    $annual,
    $monthlyEstimates,
);
$forecast = BudgetForecast::forecast($actualBasePrimary, $plan, $elapsedPlansSum);
```

4. Add row field:

```php
'monthly_template' => YearlyMonthlyTemplate::forCategory($category, $this->year, $monthlyEstimates),
```

Import `YearlyMonthlyTemplate` and `BudgetPeriod`.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/YearlyBudgetTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Budgets/ListYearlyBudget.php tests/Feature/Budgets/YearlyBudgetTest.php
git commit -m "$(cat <<'EOF'
feat(budget): yearly forecast uses closed-month actual and monthly_template

EOF
)"
```

---

### Task 6: `SaveYearlyCategoryPlan` backend endpoint

**Files:**
- Create: `app/Actions/Categories/SaveYearlyCategoryPlan.php`
- Create: `app/Http/Requests/Categories/SaveYearlyCategoryPlanRequest.php`
- Modify: `app/Http/Controllers/Categories/CategoryController.php`
- Modify: `routes/categories.php`
- Modify: `config/routes.php`
- Modify: `app/Support/Routing/LegacyRouteRedirector.php`
- Modify: `tests/Feature/Categories/CategoryEstimatesTest.php`

- [ ] **Step 1: Write failing feature tests**

Append to `tests/Feature/Categories/CategoryEstimatesTest.php`:

```php
use Illuminate\Support\Carbon;

test('user can save yearly plan with annual and bulk monthly from June', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 5,
        'amount' => 999,
    ]);

    $response = $this->actingAs($user)->patch(route('categories.estimates.yearly-plan', $food, absolute: false), [
        'year' => 2026,
        'annual_amount' => 12000,
        'monthly_amount' => 400,
    ]);

    $response->assertSessionHasNoErrors();

    expect((string) CategoryAnnualEstimate::query()->where('category_id', $food->id)->where('year', 2026)->value('amount'))->toBe('12000.00');
    expect((string) CategoryMonthlyEstimate::query()->where('category_id', $food->id)->where('year', 2026)->where('month', 5)->value('amount'))->toBe('999.00');
    expect((string) CategoryMonthlyEstimate::query()->where('category_id', $food->id)->where('year', 2026)->where('month', 6)->value('amount'))->toBe('400.00');
    expect((string) CategoryMonthlyEstimate::query()->where('category_id', $food->id)->where('year', 2026)->where('month', 12)->value('amount'))->toBe('400.00');
    expect(CategoryMonthlyEstimate::query()->where('category_id', $food->id)->where('year', 2026)->where('month', 1)->exists())->toBeFalse();

    Carbon::setTestNow();
});

test('empty monthly_amount does not change existing monthly overrides', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 8,
        'amount' => 2000,
    ]);

    $this->actingAs($user)->patch(route('categories.estimates.yearly-plan', $food, absolute: false), [
        'year' => 2026,
        'annual_amount' => 5000,
    ])->assertSessionHasNoErrors();

    expect((string) CategoryMonthlyEstimate::query()->where('category_id', $food->id)->where('year', 2026)->where('month', 8)->value('amount'))->toBe('2000.00');

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests — expect FAIL (route missing)**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Categories/CategoryEstimatesTest.php
```

- [ ] **Step 3: Create request**

`app/Http/Requests/Categories/SaveYearlyCategoryPlanRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SaveYearlyCategoryPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Category $category */
        $category = $this->route('category');

        return $this->user()?->can('update', $category) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'monthly_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Create action**

`app/Actions/Categories/SaveYearlyCategoryPlan.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\YearlyMonthlyTemplate;
use App\Telemetry\Event;
use Illuminate\Support\Facades\DB;

final class SaveYearlyCategoryPlan
{
    /**
     * @param  array{year: int, annual_amount: ?numeric-string|float|int|null, monthly_amount?: ?numeric-string|float|int|null}  $validated
     */
    public function handle(Category $category, array $validated): void
    {
        DB::transaction(function () use ($category, $validated): void {
            CategoryAnnualEstimate::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'year' => $validated['year'],
                ],
                [
                    'amount' => $validated['annual_amount'] ?? null,
                ],
            );

            $monthlyAmount = $validated['monthly_amount'] ?? null;

            if ($monthlyAmount !== null && $monthlyAmount !== '') {
                $existing = CategoryMonthlyEstimate::query()
                    ->where('category_id', $category->id)
                    ->where('year', $validated['year'])
                    ->get()
                    ->keyBy('month');

                foreach (YearlyMonthlyTemplate::eligibleMonths($validated['year']) as $month) {
                    $row = $existing->get($month);

                    if (YearlyMonthlyTemplate::hasOverride($month, $row)) {
                        continue;
                    }

                    CategoryMonthlyEstimate::query()->updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'year' => $validated['year'],
                            'month' => $month,
                        ],
                        [
                            'amount' => $monthlyAmount,
                        ],
                    );
                }
            }

            Event::record('category_estimate_yearly_plan_saved', [
                'category_id' => $category->id,
                'year' => $validated['year'],
            ], $category->user_id);
        });
    }
}
```

- [ ] **Step 5: Wire controller + routes**

`CategoryController` — add method (mirror `saveAnnualEstimate`):

```php
public function saveYearlyCategoryPlan(
    SaveYearlyCategoryPlanRequest $request,
    Category $category,
    SaveYearlyCategoryPlan $saveYearlyCategoryPlan,
): RedirectResponse {
    $this->authorize('update', $category);
    $saveYearlyCategoryPlan->handle($category, $request->validated());

    return back()->with('toast', [
        'type' => 'success',
        'message_key' => 'categories.toast.estimate_saved',
    ]);
}
```

`routes/categories.php`:

```php
Route::patch(route_path('categories.estimates.yearly-plan'), [CategoryController::class, 'saveYearlyCategoryPlan'])
    ->name('categories.estimates.yearly-plan');
```

`config/routes.php` segments:

```php
'categories.estimates.yearly-plan' => 'kategorie/{category}/szacunki/plan-roczny',
// en:
'categories.estimates.yearly-plan' => 'categories/{category}/estimates/yearly-plan',
```

`LegacyRouteRedirector` — add to categories pair array and map:

```php
'categories/{category}/estimates/yearly-plan' => route_path('categories.estimates.yearly-plan'),
```

- [ ] **Step 6: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Categories/CategoryEstimatesTest.php
```

Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Categories/SaveYearlyCategoryPlan.php app/Http/Requests/Categories/SaveYearlyCategoryPlanRequest.php app/Http/Controllers/Categories/CategoryController.php routes/categories.php config/routes.php app/Support/Routing/LegacyRouteRedirector.php tests/Feature/Categories/CategoryEstimatesTest.php
git commit -m "$(cat <<'EOF'
feat(categories): add yearly-plan endpoint for annual and bulk monthly save

EOF
)"
```

---

### Task 7: Frontend — `YearlyPlanEditCell` + wiring

**Files:**
- Create: `resources/js/components/budget/YearlyPlanEditCell.vue`
- Modify: `resources/js/components/budget/BudgetCategorySection.vue`
- Modify: `resources/js/pages/budget/Yearly.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add i18n keys**

`pl.json` inside `budget.yearly`:

```json
"monthlyPlanPlaceholder": "Opcjonalnie",
"monthlyPlanLabel": "Plan miesięczny"
```

`en.json`:

```json
"monthlyPlanPlaceholder": "Optional",
"monthlyPlanLabel": "Monthly plan"
```

- [ ] **Step 2: Create `YearlyPlanEditCell.vue`**

Create `resources/js/components/budget/YearlyPlanEditCell.vue` (based on `EditableEstimateCell`, dual inputs):

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Check, Pencil, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    annualPlan: string | null;
    monthlyTemplate: string | null;
    currency: CurrencyDisplay;
    inputIdPrefix: string;
    annualPlaceholder: string;
    monthlyPlaceholder: string;
    editLabel: string;
    saveLabel: string;
    cancelLabel: string;
    isEditing: boolean;
}>();

const emit = defineEmits<{
    'start-edit': [];
    cancel: [];
    save: [annualRaw: string, monthlyRaw: string];
}>();

const annualDraft = ref('');
const monthlyDraft = ref('');
const error = ref<string | null>(null);

watch(
    () => props.isEditing,
    (editing) => {
        if (editing) {
            annualDraft.value = props.annualPlan ?? '';
            monthlyDraft.value = props.monthlyTemplate ?? '';
            error.value = null;
        }
    },
);

function isValidAmount(raw: string): boolean {
    const trimmed = raw.trim();
    if (trimmed === '') return true;
    return /^\d+([.,]\d{1,2})?$/.test(trimmed);
}

function onSave() {
    if (!isValidAmount(annualDraft.value) || !isValidAmount(monthlyDraft.value)) {
        error.value = 'invalid';
        return;
    }
    error.value = null;
    emit('save', annualDraft.value, monthlyDraft.value);
}

function onCancel() {
    error.value = null;
    emit('cancel');
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        event.preventDefault();
        onSave();
    }
    if (event.key === 'Escape') {
        event.preventDefault();
        onCancel();
    }
}
</script>

<template>
    <div v-if="!isEditing" class="flex items-center gap-1">
        <span class="w-28 shrink-0 text-left tabular-nums">{{ formatMoney(annualPlan, currency) }}</span>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="editLabel" @click="emit('start-edit')">
            <Pencil class="h-4 w-4 text-muted-foreground" />
        </Button>
    </div>
    <div v-else class="flex items-center gap-1">
        <Input
            :id="`${inputIdPrefix}-annual`"
            v-model="annualDraft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="annualPlaceholder"
            :aria-invalid="error !== null"
            @keydown="onKeydown"
        />
        <Input
            :id="`${inputIdPrefix}-monthly`"
            v-model="monthlyDraft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="monthlyPlaceholder"
            :aria-invalid="error !== null"
            @keydown="onKeydown"
        />
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="saveLabel" @click="onSave">
            <Check class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
        </Button>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="cancelLabel" @click="onCancel">
            <X class="h-4 w-4 text-muted-foreground" />
        </Button>
    </div>
</template>
```

- [ ] **Step 3: Update `BudgetCategorySection.vue`**

1. Import `YearlyPlanEditCell`.
2. Extend `BudgetRow` with `monthly_template?: string | null`.
3. Extend emit: `save: [row: BudgetRow, annualRaw: string, monthlyRaw: string]` when yearly (or overload — yearly handler receives both).
4. In plan `<td>`, use `v-if="variant === 'yearly'"` → `YearlyPlanEditCell`, else `EditableEstimateCell`.
5. Pass `monthly_template`, `annual_plan`, yearly placeholders from parent.

- [ ] **Step 4: Update `Yearly.vue`**

1. Extend `BudgetRow` with `monthly_template?: string | null`.
2. Replace `saveAnnualEstimate` with:

```ts
function saveYearlyPlan(
    row: { category_id: number; annual_plan?: string | null },
    annualRaw: string,
    monthlyRaw: string,
) {
    const annualTrimmed = annualRaw.trim();
    const monthlyTrimmed = monthlyRaw.trim();
    const annualNormalized = annualTrimmed.replace(',', '.');
    const monthlyNormalized = monthlyTrimmed.replace(',', '.');
    const currentAnnual = row.annual_plan ?? '';

    const annualUnchanged = annualNormalized === currentAnnual || (annualNormalized === '' && currentAnnual === '');
    const monthlyEmpty = monthlyTrimmed === '';

    if (annualUnchanged && monthlyEmpty) {
        editingCategoryId.value = null;
        return;
    }

    const payload: Record<string, unknown> = {
        year: props.year,
        annual_amount: annualTrimmed === '' ? null : annualNormalized,
    };

    if (!monthlyEmpty) {
        payload.monthly_amount = monthlyNormalized;
    }

    router.patch(route('categories.estimates.yearly-plan', row.category_id), payload, {
        preserveScroll: true,
        onFinish: () => {
            editingCategoryId.value = null;
        },
    });
}
```

3. Wire `@save="saveYearlyPlan"` — handler receives `(row, annualRaw, monthlyRaw)`.
4. Update scoped CSS: `--budget-col-plan: 18rem;`
5. Pass `monthlyPlanPlaceholder` prop to sections.

- [ ] **Step 5: Lint**

```bash
npm run lint -- resources/js/components/budget/YearlyPlanEditCell.vue resources/js/components/budget/BudgetCategorySection.vue resources/js/pages/budget/Yearly.vue
```

- [ ] **Step 6: Run backend regression**

```bash
./vendor/bin/sail artisan test --compact --filter=YearlyBudget
./vendor/bin/sail artisan test --compact --filter=Categories
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/budget/YearlyPlanEditCell.vue resources/js/components/budget/BudgetCategorySection.vue resources/js/pages/budget/Yearly.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "$(cat <<'EOF'
feat(budget): dual annual and monthly plan edit on yearly view

EOF
)"
```

---

### Task 8: PRD alignment + final verification

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: Update PRD FR-C4 wording**

In `.docs/prd.md`, change monthly default description from `roczny ÷ 12` to explicit zero / optional override. Update dictionary entry for **Szacunek miesięczny** accordingly.

- [ ] **Step 2: Full test suite (scoped)**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/
./vendor/bin/sail artisan test --compact tests/Feature/Categories/CategoryEstimatesTest.php
```

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add .docs/prd.md
git commit -m "$(cat <<'EOF'
docs(prd): monthly plan default is explicit zero not annual÷12

EOF
)"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Dual inline edit UI | Task 7 |
| Bulk monthly eligible months | Task 4, 6 |
| Override protection | Task 6 tests |
| Empty monthly no-op | Task 6 tests |
| Display monthly = 0 | Task 1, 3 |
| Forecast annual÷12 | Task 1, 2, 5 |
| Forecast excludes current month actual | Task 2, 5 |
| Execution/progress unchanged | Task 5 (keeps full-year actual) |
| `monthly_template` | Task 4, 5 |
| i18n | Task 7 |
| Telemetry | Task 6 |
| PRD update | Task 8 |

No TBD placeholders. Type names consistent (`annual_amount`, `monthly_amount`, `monthly_template`).

---

## Manual smoke checklist

- [ ] Yearly view: pencil → two inputs; save annual only; save annual + monthly
- [ ] Monthly view: category with annual but no override shows `0,00`
- [ ] Monthly view: override still shows custom amount
- [ ] Forecast column updates after dual save (June scenario)
- [ ] Month with manual override not overwritten by bulk

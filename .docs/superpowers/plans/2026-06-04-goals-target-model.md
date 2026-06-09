# Goals target model refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace year-scoped goal estimates with a timeless target-envelope model (target amount, planning mode, icon/color, cumulative progress, manual archive) while keeping transfer-based tracking unchanged.

**Architecture:** Migration adds fields to `goals`, migrates annual estimate data, drops estimate tables. Pure calculation classes in `Support/Goals/` (`GoalBalance`, `GoalPlanningProjection`) replace `GoalPlanAmount`. Goals UI mirrors Categories (create/edit pages, reorder, badge). Monthly budget reads plan from goal fields (read-only in budget view).

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests/migrations.

**Spec:** `.docs/superpowers/specs/2026-06-04-goals-target-model-design.md`

**Suggested branch:** `feature/goals-target-model` (from `develop`)

---

## File map

| Action | Path |
|--------|------|
| Modify | `.docs/prd.md` — FR-G2, §5 Goal model, dictionary, telemetry |
| Modify | `.docs/checklist.md` — section 19 delta |
| Create | `database/migrations/2026_06_04_140000_refactor_goals_target_model.php` |
| Create | `app/Enums/GoalPlanningMode.php` |
| Create | `app/Support/Goals/GoalBalance.php` |
| Create | `app/Support/Goals/GoalPlanningProjection.php` |
| Create | `app/Data/Goals/GoalFormOptions.php` |
| Create | `app/Actions/Goals/ReorderGoals.php` |
| Create | `app/Http/Requests/Goals/ReorderGoalsRequest.php` |
| Modify | `app/Models/Goal.php` |
| Delete | `app/Models/GoalAnnualEstimate.php`, `GoalMonthlyEstimate.php` |
| Delete | `app/Actions/Goals/SaveAnnualEstimate.php`, `SaveMonthlyEstimate.php` |
| Delete | `app/Http/Requests/Goals/SaveAnnualEstimateRequest.php`, `SaveMonthlyEstimateRequest.php` |
| Delete | `app/Support/Goals/GoalPlanAmount.php` |
| Modify | `database/factories/GoalFactory.php` |
| Modify | `app/Actions/Goals/StoreGoal.php`, `UpdateGoal.php`, `ListGoals.php` |
| Modify | `app/Actions/Goals/MigrateLegacySavingsEstimate.php` |
| Modify | `app/Http/Requests/Goals/StoreGoalRequest.php`, `UpdateGoalRequest.php` |
| Modify | `app/Http/Resources/Goals/GoalResource.php` |
| Modify | `app/Http/Controllers/Goals/GoalController.php` |
| Modify | `routes/goals.php` |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Create | `resources/js/components/goals/GoalBadge.vue` |
| Create | `resources/js/components/goals/GoalProgressBar.vue` |
| Create | `resources/js/pages/goals/Create.vue` |
| Create | `resources/js/pages/goals/Edit.vue` |
| Modify | `resources/js/pages/goals/Index.vue` |
| Modify | `resources/js/pages/budget/Monthly.vue` |
| Modify | `resources/js/locales/pl.json`, `en.json` |
| Create | `tests/Unit/Support/Goals/GoalBalanceTest.php` |
| Create | `tests/Unit/Support/Goals/GoalPlanningProjectionTest.php` |
| Create | `tests/Feature/Goals/GoalPlanningTest.php` |
| Create | `tests/Feature/Goals/GoalReorderTest.php` |
| Create | `tests/Feature/Goals/GoalArchiveTest.php` |
| Create | `tests/Feature/Goals/GoalTargetModelMigrationTest.php` |
| Delete | `tests/Feature/Goals/GoalEstimatesTest.php` |
| Modify | `tests/Feature/Goals/GoalCrudTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Modify | `tests/Feature/Goals/MigrateLegacySavingsEstimateTest.php` |

---

### Task 1: `GoalPlanningMode` enum

**Files:**
- Create: `app/Enums/GoalPlanningMode.php`

- [ ] **Step 1: Create enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalPlanningMode: string
{
    case Monthly = 'monthly';
    case ByDate = 'by_date';
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Enums/GoalPlanningMode.php
git commit -m "feat(goals): add GoalPlanningMode enum"
```

---

### Task 2: `GoalBalance` cumulative metrics (TDD)

**Files:**
- Create: `app/Support/Goals/GoalBalance.php`
- Create: `tests/Unit/Support/Goals/GoalBalanceTest.php`

- [ ] **Step 1: Write failing unit test**

```php
<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Goals\GoalBalance;
use Illuminate\Support\Str;

test('cumulative balance sums savings transfer legs across all months', function () {
    $user = User::factory()->create();
    $ror = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Ror]);
    $savings = Account::factory()->create(['user_id' => $user->id, 'type' => AccountType::Savings]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    $transferId = (string) Str::uuid();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $ror->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId,
        'amount' => '-300.00',
        'booked_at' => '2026-01-15',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId,
        'amount' => '300.00',
        'booked_at' => '2026-01-15',
    ]);

    $transferId2 = (string) Str::uuid();
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId2,
        'amount' => '-100.00',
        'booked_at' => '2026-03-10',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $ror->id,
        'goal_id' => $goal->id,
        'transfer_id' => $transferId2,
        'amount' => '100.00',
        'booked_at' => '2026-03-10',
    ]);

    $result = GoalBalance::cumulative($user, $goal);

    expect($result)->toBe([
        'saved_total' => '300.00',
        'released_total' => '100.00',
        'balance' => '200.00',
    ]);
});
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Goals/GoalBalanceTest.php`

Expected: FAIL — class `GoalBalance` not found

- [ ] **Step 3: Implement `GoalBalance`**

```php
<?php

declare(strict_types=1);

namespace App\Support\Goals;

use App\Enums\AccountType;
use App\Models\Goal;
use App\Models\User;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;

final class GoalBalance
{
    /**
     * @return array{saved_total: string, released_total: string, balance: string}
     */
    public static function cumulative(User $user, Goal $goal): array
    {
        $savedQuery = BudgetTransactionQuery::forUser($user);
        $savedSum = $savedQuery
            ->where('goal_id', $goal->id)
            ->whereNotNull('transfer_id')
            ->where('amount', '>', 0)
            ->whereHas('account', fn ($q) => $q->where('type', AccountType::Savings))
            ->sum('amount');

        $savedTotal = TransactionDedupe::amountToDecimalString((string) $savedSum);

        $releasedQuery = BudgetTransactionQuery::forUser($user);
        $releasedSum = $releasedQuery
            ->where('goal_id', $goal->id)
            ->whereNotNull('transfer_id')
            ->where('amount', '<', 0)
            ->whereHas('account', fn ($q) => $q->where('type', AccountType::Savings))
            ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
            ->value('total');

        $releasedTotal = TransactionDedupe::amountToDecimalString((string) $releasedSum);
        $balance = bcsub($savedTotal, $releasedTotal, 2);

        return [
            'saved_total' => $savedTotal,
            'released_total' => $releasedTotal,
            'balance' => $balance,
        ];
    }

    public static function isCompleted(Goal $goal, string $balance): bool
    {
        if ($goal->target_amount === null) {
            return false;
        }

        return bccomp($balance, (string) $goal->target_amount, 2) >= 0;
    }

    public static function progressPercent(Goal $goal, string $balance): ?int
    {
        if ($goal->target_amount === null || bccomp((string) $goal->target_amount, '0', 2) === 0) {
            return null;
        }

        $ratio = bcdiv($balance, (string) $goal->target_amount, 4);
        $percent = (int) bcmul($ratio, '100', 0);

        return min(100, max(0, $percent));
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Goals/GoalBalanceTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Support/Goals/GoalBalance.php tests/Unit/Support/Goals/GoalBalanceTest.php
git commit -m "feat(goals): add cumulative GoalBalance helper"
```

---

### Task 3: `GoalPlanningProjection` (TDD)

**Files:**
- Create: `app/Support/Goals/GoalPlanningProjection.php`
- Create: `tests/Unit/Support/Goals/GoalPlanningProjectionTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Enums\GoalPlanningMode;
use App\Models\Goal;
use Carbon\Carbon;

test('by_date mode recommends monthly from remaining amount and months left', function () {
    Carbon::setTestNow('2026-06-04');

    $goal = Goal::factory()->make([
        'target_amount' => '5000.00',
        'planning_mode' => GoalPlanningMode::ByDate,
        'target_date' => '2026-10-31',
        'monthly_contribution' => null,
    ]);

    $recommended = GoalPlanningProjection::recommendedMonthly($goal, '2000.00');

    // remaining 3000, months Jun–Oct inclusive = 5
    expect($recommended)->toBe('600.00');
});

test('monthly mode projects completion using effective savings rate', function () {
    Carbon::setTestNow('2026-06-30');

    $goal = Goal::factory()->make([
        'target_amount' => '1200.00',
        'planning_mode' => GoalPlanningMode::Monthly,
        'monthly_contribution' => '200.00',
        'created_at' => Carbon::parse('2026-04-01'),
    ]);

    $monthlyNets = [
        '2026-04' => '100.00',
        '2026-05' => '200.00',
    ];

    $projected = GoalPlanningProjection::projectedCompletionDate($goal, '300.00', $monthlyNets);

    // remaining 900, effective rate (100+200)/2 = 150, ceil(900/150)=6 months -> 2026-12-31
    expect($projected?->toDateString())->toBe('2026-12-31');
});
```

Add `use App\Support\Goals\GoalPlanningProjection;` at top.

- [ ] **Step 2: Run tests — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Goals/GoalPlanningProjectionTest.php`

- [ ] **Step 3: Implement projection helper**

```php
<?php

declare(strict_types=1);

namespace App\Support\Goals;

use App\Enums\GoalPlanningMode;
use App\Models\Goal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class GoalPlanningProjection
{
    public static function recommendedMonthly(Goal $goal, string $balance): ?string
    {
        if ($goal->planning_mode !== GoalPlanningMode::ByDate || $goal->target_amount === null || $goal->target_date === null) {
            return null;
        }

        $remaining = bcsub((string) $goal->target_amount, $balance, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return '0.00';
        }

        $now = Carbon::now()->startOfMonth();
        $targetMonth = Carbon::parse($goal->target_date)->startOfMonth();

        if ($targetMonth->lt($now)) {
            return $remaining;
        }

        $monthsLeft = max(1, $now->diffInMonths($targetMonth) + 1);

        return bcdiv($remaining, (string) $monthsLeft, 2);
    }

    /**
     * @param  array<string, string>  $monthlyNets  keys `YYYY-MM`, values net saved in month
     */
    public static function projectedCompletionDate(Goal $goal, string $balance, array $monthlyNets): ?CarbonInterface
    {
        if ($goal->planning_mode !== GoalPlanningMode::Monthly || $goal->target_amount === null || $goal->monthly_contribution === null) {
            return null;
        }

        $remaining = bcsub((string) $goal->target_amount, $balance, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return Carbon::today();
        }

        $positiveNets = array_values(array_filter($monthlyNets, fn (string $net): bool => bccomp($net, '0', 2) > 0));

        if ($positiveNets === []) {
            $effectiveRate = (string) $goal->monthly_contribution;
        } else {
            $sum = array_reduce($positiveNets, fn (string $carry, string $net): string => bcadd($carry, $net, 2), '0.00');
            $effectiveRate = bcdiv($sum, (string) count($positiveNets), 2);
        }

        if (bccomp($effectiveRate, '0', 2) <= 0) {
            return null;
        }

        $monthsNeeded = (int) ceil((float) bcdiv($remaining, $effectiveRate, 4));
        $monthsNeeded = max(1, $monthsNeeded);

        return Carbon::now()->startOfMonth()->addMonths($monthsNeeded)->endOfMonth();
    }

    public static function monthlyPlanForBudget(Goal $goal, string $balance): ?string
    {
        return match ($goal->planning_mode) {
            GoalPlanningMode::Monthly => $goal->monthly_contribution !== null ? (string) $goal->monthly_contribution : null,
            GoalPlanningMode::ByDate => self::recommendedMonthly($goal, $balance),
            default => null,
        };
    }

    public static function isOverdue(Goal $goal, string $balance): bool
    {
        if ($goal->planning_mode !== GoalPlanningMode::ByDate || $goal->target_date === null || $goal->target_amount === null) {
            return false;
        }

        return Carbon::parse($goal->target_date)->isPast() && bccomp($balance, (string) $goal->target_amount, 2) < 0;
    }

    /**
     * Build monthly net map for projection from transaction query results.
     *
     * @param  iterable<object{ym: string, net: string}>  $rows
     * @return array<string, string>
     */
    public static function monthlyNetMapFromRows(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row->ym] = TransactionDedupe::amountToDecimalString((string) $row->net);
        }

        return $map;
    }
}
```

Add `use App\Support\Transactions\TransactionDedupe;` import.

- [ ] **Step 4: Run tests — expect PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Goals/GoalPlanningProjectionTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Support/Goals/GoalPlanningProjection.php tests/Unit/Support/Goals/GoalPlanningProjectionTest.php
git commit -m "feat(goals): add planning projection helpers"
```

---

### Task 4: Database migration + model update

**Files:**
- Create: `database/migrations/2026_06_04_140000_refactor_goals_target_model.php`
- Modify: `app/Models/Goal.php`
- Modify: `database/factories/GoalFactory.php`

- [ ] **Step 1: Write migration**

Migration steps in `up()`:

1. Add columns to `goals`:
   - `icon` string default `'target'`
   - `color` string default `'#6366f1'` (first palette color)
   - `target_amount` decimal(12,2) nullable
   - `planning_mode` string nullable
   - `monthly_contribution` decimal(12,2) nullable
   - `target_date` date nullable
   - `is_archived` boolean default false

2. Data migration (use `now()->year`):
   ```php
   $year = (int) now()->year;
   DB::table('goal_annual_estimates')
       ->where('year', $year)
       ->whereNotNull('amount')
       ->orderBy('id')
       ->each(function ($row) use ($year): void {
           DB::table('goals')->where('id', $row->goal_id)->update([
               'target_amount' => $row->amount,
               'planning_mode' => 'monthly',
               'monthly_contribution' => bcdiv((string) $row->amount, '12', 2),
           ]);
       });
   ```

3. Assign rotating colors by `sort_order` using `CategoryColors::values()` for goals missing color.

4. Drop `goal_monthly_estimates`, `goal_annual_estimates`.

`down()` — recreate estimate tables and drop new columns (best-effort; acceptable for feature branch).

- [ ] **Step 2: Update `Goal` model**

- Add fillable: `icon`, `color`, `target_amount`, `planning_mode`, `monthly_contribution`, `target_date`, `is_archived`
- Casts: `planning_mode` → `GoalPlanningMode::class`, `target_amount`/`monthly_contribution` → `decimal:2`, `target_date` → `date`, `is_archived` → `boolean`
- Remove `annualEstimates()` and `monthlyEstimates()` relations
- Add scope `active()`: `where('is_archived', false)`

- [ ] **Step 3: Update factory**

```php
return [
    'user_id' => User::factory(),
    'name' => fake()->word(),
    'icon' => 'target',
    'color' => '#6366f1',
    'sort_order' => 10,
    'target_amount' => null,
    'planning_mode' => null,
    'monthly_contribution' => null,
    'target_date' => null,
    'is_archived' => false,
];
```

Add states: `withTargetMonthly(string $amount, string $monthly)` and `withTargetByDate(string $amount, string $date)`.

- [ ] **Step 4: Run migration**

Run: `./vendor/bin/sail artisan migrate`

Expected: new columns; estimate tables dropped.

- [ ] **Step 5: Delete obsolete models**

Remove files:
- `app/Models/GoalAnnualEstimate.php`
- `app/Models/GoalMonthlyEstimate.php`

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_04_140000_refactor_goals_target_model.php app/Models/Goal.php database/factories/GoalFactory.php
git commit -m "feat(goals): migrate to target model schema"
```

---

### Task 5: Form requests + store/update actions

**Files:**
- Modify: `app/Http/Requests/Goals/StoreGoalRequest.php`
- Modify: `app/Http/Requests/Goals/UpdateGoalRequest.php`
- Modify: `app/Actions/Goals/StoreGoal.php`
- Modify: `app/Actions/Goals/UpdateGoal.php`
- Create: `tests/Feature/Goals/GoalPlanningTest.php`

- [ ] **Step 1: Write failing feature tests for validation**

```php
test('goal with target requires planning mode and monthly contribution', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'target_amount' => '5000',
        'planning_mode' => 'monthly',
    ])->assertSessionHasErrors('monthly_contribution');
});

test('goal rejects both monthly contribution and target date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'target_amount' => '5000',
        'planning_mode' => 'monthly',
        'monthly_contribution' => '200',
        'target_date' => '2026-12-31',
    ])->assertSessionHasErrors('target_date');
});
```

- [ ] **Step 2: Run tests — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalPlanningTest.php`

- [ ] **Step 3: Update `StoreGoalRequest` rules**

Reuse `CategoryIcons::values()` and `CategoryColors::values()` for icon/color validation.

Rules sketch:

```php
'name' => ['required', 'string', 'max:100', Rule::unique(...)],
'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
'color' => ['required', 'string', Rule::in(CategoryColors::values())],
'target_amount' => ['nullable', 'numeric', 'min:0'],
'planning_mode' => ['nullable', Rule::enum(GoalPlanningMode::class), 'required_with:target_amount'],
'monthly_contribution' => ['nullable', 'numeric', 'min:0', 'required_if:planning_mode,monthly', 'prohibited_if:planning_mode,by_date'],
'target_date' => ['nullable', 'date', 'after_or_equal:today', 'required_if:planning_mode,by_date', 'prohibited_if:planning_mode,monthly'],
```

Add `prepareForValidation()` to null planning fields when `target_amount` empty.

`UpdateGoalRequest` — same rules + unique name ignoring current goal; allow `is_archived` boolean; relax `target_date` `after_or_equal:today` on update (use `Rule::excludeIf` or conditional).

- [ ] **Step 4: Update `StoreGoal` / `UpdateGoal`**

Persist all validated fields. `UpdateGoal` records `goal_archived` / `goal_unarchived` telemetry when `is_archived` toggles.

- [ ] **Step 5: Run tests — expect PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalPlanningTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Goals/ app/Actions/Goals/StoreGoal.php app/Actions/Goals/UpdateGoal.php tests/Feature/Goals/GoalPlanningTest.php
git commit -m "feat(goals): validate and persist target planning fields"
```

---

### Task 6: Remove estimate endpoints + dead code

**Files:**
- Delete: estimate actions, requests, `GoalPlanAmount.php`, `GoalEstimatesTest.php`
- Modify: `app/Http/Controllers/Goals/GoalController.php`
- Modify: `routes/goals.php`
- Modify: `app/Actions/Goals/MigrateLegacySavingsEstimate.php`

- [ ] **Step 1: Remove controller methods** `saveAnnualEstimate`, `saveMonthlyEstimate` and estimate routes from `routes/goals.php`.

- [ ] **Step 2: Update `MigrateLegacySavingsEstimate`**

When creating default goal, set:
```php
'icon' => 'piggy-bank',
'color' => CategoryColors::values()[0],
'target_amount' => $annualAmount,
'planning_mode' => GoalPlanningMode::Monthly,
'monthly_contribution' => bcdiv($annualAmount, '12', 2),
```
Remove writes to `GoalAnnualEstimate` / `GoalMonthlyEstimate`.

- [ ] **Step 3: Delete obsolete files** listed in file map.

- [ ] **Step 4: Run affected tests**

Run: `./vendor/bin/sail artisan test --compact --filter=Goals`

Fix any broken imports/references.

- [ ] **Step 5: Commit**

```bash
git commit -am "refactor(goals): remove year-scoped estimate layer"
```

---

### Task 7: `GoalResource`, `ListGoals`, monthly budget

**Files:**
- Modify: `app/Http/Resources/Goals/GoalResource.php`
- Modify: `app/Actions/Goals/ListGoals.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: Extend `GoalResource`**

Accept optional precomputed `$balance` array or compute inside using `GoalBalance` + `GoalPlanningProjection` when `$request->user()` present.

Return shape:

```php
[
    'id', 'name', 'icon', 'color', 'sort_order',
    'target_amount', 'planning_mode', 'monthly_contribution', 'target_date',
    'is_archived', 'is_completed', 'is_overdue', 'progress_percent',
    'balance', 'saved_total', 'released_total',
    'recommended_monthly', 'projected_completion_date',
]
```

Dates as `Y-m-d` strings; decimals as strings.

- [ ] **Step 2: Update `ListGoals`**

```php
public function handle(User $user, ?string $filter = 'active'): void
{
    $query = Goal::query()->forUser($user->id)->ordered();

    match ($filter) {
        'archived' => $query->where('is_archived', true),
        'active' => $query->where('is_archived', false),
        default => null,
    };

    $this->goals = $query->get();
}
```

Remove `$year` parameter.

- [ ] **Step 3: Refactor `ListMonthlyBudget::buildGoalRows`**

- Filter `->where('is_archived', false)`
- Remove estimate queries
- For each goal: `$balance = GoalBalance::cumulative(...)`
- `'monthly_plan' => GoalPlanningProjection::monthlyPlanForBudget($goal, $balance['balance'])`
- Add optional: `'progress_hint' => ...`, `'icon'`, `'color'`

- [ ] **Step 4: Update `MonthlyBudgetTest`**

Replace `GoalMonthlyEstimate` setup with:

```php
$goal = Goal::factory()->create([
    'user_id' => $user->id,
    'name' => 'Wakacje',
    'target_amount' => '6000.00',
    'planning_mode' => GoalPlanningMode::Monthly,
    'monthly_contribution' => '500.00',
]);
```

Assert `monthly_plan === '500.00'`.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Resources/Goals/GoalResource.php app/Actions/Goals/ListGoals.php app/Actions/Budgets/ListMonthlyBudget.php tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "feat(goals): expose cumulative metrics in API and budget"
```

---

### Task 8: Controller routes — create/edit/reorder

**Files:**
- Create: `app/Data/Goals/GoalFormOptions.php`
- Create: `app/Actions/Goals/ReorderGoals.php`
- Create: `app/Http/Requests/Goals/ReorderGoalsRequest.php`
- Modify: `app/Http/Controllers/Goals/GoalController.php`
- Modify: `routes/goals.php`
- Create: `tests/Feature/Goals/GoalReorderTest.php`
- Create: `tests/Feature/Goals/GoalArchiveTest.php`

- [ ] **Step 1: `GoalFormOptions`** — mirror `CategoryFormOptions` (reuse `CategoryIcons`, `CategoryColors`).

- [ ] **Step 2: `ReorderGoals`** — mirror `ReorderCategories` but without type split:

```php
public function handle(User $user, array $ids): void
{
    foreach ($ids as $index => $id) {
        Goal::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->update(['sort_order' => ($index + 1) * 10]);
    }
}
```

- [ ] **Step 3: Update `GoalController`**

- `index`: accept `?filter=active|archived|all`; remove year; pass `filter`
- `create`: render `goals/Create` with `GoalFormOptions`
- `store`: redirect `to_route('goals.index')`
- `edit`: render `goals/Edit` with goal resource + form options
- `update`: redirect `to_route('goals.index')` unless only `is_archived` toggle → `back()`
- `reorder`: new method

- [ ] **Step 4: Update routes**

```php
Route::resource('goals', GoalController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
Route::patch('goals/reorder', [GoalController::class, 'reorder'])->name('goals.reorder');
```

- [ ] **Step 5: Feature tests**

`GoalReorderTest` — POST patch reorder changes sort_order.
`GoalArchiveTest` — PATCH `is_archived: true` hides from default index and monthly budget.

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/

- [ ] **Step 7: Commit**

```bash
git commit -am "feat(goals): add create/edit pages and reorder"
```

---

### Task 9: Frontend — goals UI

**Files:**
- Create: `resources/js/components/goals/GoalBadge.vue`
- Create: `resources/js/components/goals/GoalProgressBar.vue`
- Create: `resources/js/pages/goals/Create.vue`
- Create: `resources/js/pages/goals/Edit.vue`
- Modify: `resources/js/pages/goals/Index.vue`
- Modify: `resources/js/locales/pl.json`, `en.json`

- [ ] **Step 1: `GoalBadge.vue`**

Copy structure from `CategoryBadge.vue` — same props (`name`, `icon`, `color`, `size`).

- [ ] **Step 2: `GoalProgressBar.vue`**

Props: `percent: number | null`, `balance: string`, `targetAmount: string | null`.

Render `<div role="progressbar">` with Tailwind width from `percent`; show text `balance / targetAmount` when target set.

- [ ] **Step 3: `Create.vue` / `Edit.vue`**

Mirror categories create/edit:
- Name, icon picker, color picker (reuse `CategoryIconPicker`, `CategoryColorPicker`)
- Optional target amount input
- When target > 0: radio/toggle `planning_mode` (`monthly` | `by_date`)
- Conditional field: monthly contribution OR date input
- Read-only computed hints from server on Edit (projected date / recommended monthly)
- Edit: archive toggle checkbox

- [ ] **Step 4: Refactor `Index.vue`**

- Remove year selector and inline create form / annual estimate inputs
- Header action: Link to `goals.create` (like categories)
- Filter tabs: active / archived / all
- Draggable list (`VueDraggable`) → `goals.reorder`
- Each row: GoalBadge, GoalProgressBar (if target), balance text, status badges (completed, overdue), edit link, archive button, delete

- [ ] **Step 5: Locales**

Add keys under `goals.*`:
- `fields.targetAmount`, `fields.monthlyContribution`, `fields.targetDate`, `fields.planningMode`
- `planning.monthly`, `planning.by_date`
- `status.completed`, `status.overdue`, `status.archived`
- `filters.active`, `filters.archived`, `filters.all`
- `create.title`, `edit.title`
Remove `annualEstimate` keys.

- [ ] **Step 6: Manual smoke**

Run: `./vendor/bin/sail npm run build` (or confirm dev server)

Visit `/goals`, create goal with target + monthly, verify progress bar after transfer.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/goals/ resources/js/pages/goals/ resources/js/locales/
git commit -m "feat(goals): redesign goals UI with progress and planning"
```

---

### Task 10: Monthly budget frontend

**Files:**
- Modify: `resources/js/pages/budget/Monthly.vue`

- [ ] **Step 1: Remove `saveGoalMonthlyEstimate` function and editable plan input for goals.**

- [ ] **Step 2: Display goal plan as read-only** (`formatMoney(row.monthly_plan)`).

- [ ] **Step 3: Add optional progress hint column**

Show `{balance_cumulative} / {target_amount}` when `row.progress_hint` or equivalent props present from backend.

- [ ] **Step 4: Show GoalBadge** in goal name column when icon/color passed.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/budget/Monthly.vue
git commit -m "feat(budget): read-only goal plans from target model"
```

---

### Task 11: Migration test + CRUD test updates

**Files:**
- Create: `tests/Feature/Goals/GoalTargetModelMigrationTest.php`
- Modify: `tests/Feature/Goals/GoalCrudTest.php`
- Modify: `tests/Feature/Goals/MigrateLegacySavingsEstimateTest.php`

- [ ] **Step 1: Migration test**

Seed goal + annual estimate before migration (use `Schema` or test-specific migration rollback/refresh with factory inserting into old table if needed — prefer testing migration class logic via `artisan migrate` on fresh DB with legacy data inserted in test setup before migration runs).

Assert after migrate: goal has `target_amount`, `monthly_contribution`, estimate tables gone.

- [ ] **Step 2: Update `GoalCrudTest`**

Store now requires icon/color:

```php
$this->actingAs($user)->post(route('goals.store'), [
    'name' => 'Wakacje',
    'icon' => 'target',
    'color' => '#6366f1',
])->assertRedirect(route('goals.index'));
```

- [ ] **Step 3: Run full Goals + Budget test suite**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/ tests/Feature/Budgets/MonthlyBudgetTest.php tests/Unit/Support/Goals/

- [ ] **Step 4: Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git commit -am "test(goals): cover target model migration and CRUD"
```

---

### Task 12: PRD + checklist

**Files:**
- Modify: `.docs/prd.md`
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Update PRD** per spec § PRD delta (dictionary, §5 Goal, FR-G2, FR-C5 plan column, §7 Cele screen, telemetry).

- [ ] **Step 2: Update checklist section 19** — note target model refactor replacing estimates.

- [ ] **Step 3: Commit**

```bash
git add .docs/prd.md .docs/checklist.md
git commit -m "docs: update PRD and checklist for goals target model"
```

---

### Task 13: Final verification

- [ ] **Step 1: Full test suite**

Run: `./vendor/bin/sail artisan test --compact`

Expected: all pass.

- [ ] **Step 2: PHPStan (if types touched broadly)**

Run: `./vendor/bin/phpstan analyse`

- [ ] **Step 3: Reconcile checklist checkboxes** for shipped items.

---

## Self-review

| Spec requirement | Task |
|------------------|------|
| Drop estimate tables | Task 4, 6 |
| Goal fields (icon, color, target, modes) | Task 4, 5 |
| Cumulative balance | Task 2, 7 |
| recommended_monthly (by_date) | Task 3, 7 |
| projected_completion_date (monthly) | Task 3, 7 |
| Open-ended — no progress bar | Task 9 (GoalProgressBar `v-if="targetAmount"`) |
| Completed badge | Task 7 resource, Task 9 UI |
| Manual archive | Task 5, 8, 9 |
| Categories-like CRUD UI | Task 8, 9 |
| Monthly budget plan source | Task 7, 10 |
| Migration from annual estimates | Task 4, 11 |
| PRD update | Task 12 |

No TBD placeholders remain. Type names consistent: `GoalPlanningMode`, `GoalBalance`, `GoalPlanningProjection`.

---

## Execution handoff

Plan saved to `.docs/superpowers/plans/2026-06-04-goals-target-model.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** — implement task-by-task in this session with checkpoints

Which approach?

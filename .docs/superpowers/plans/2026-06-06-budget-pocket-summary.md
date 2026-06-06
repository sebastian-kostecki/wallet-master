# Budget monthly summary — include pockets — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Include savings pocket plans and execution (saved → expenses, released → income) in the monthly budget summary card.

**Architecture:** Add `BudgetSummary::withPockets()` in `Support/Budgets/` to merge pocket row totals into an existing P&L summary. `ListMonthlyBudget` builds pocket rows first, then `fromRows()` + `withPockets()`. No frontend, controller, route, or migration changes.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Sail for tests, `bc*` decimal math.

**Spec:** `.docs/superpowers/specs/2026-06-06-budget-pocket-summary-design.md`

**Suggested branch:** `improvement/budget-pocket-summary` (from `develop`)

---

## File map

| Action | Path |
|--------|------|
| Modify | `app/Support/Budgets/BudgetSummary.php` |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Modify | `tests/Unit/Support/Budgets/BudgetSummaryTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |

---

### Task 1: `BudgetSummary::withPockets` — merge totals

**Files:**
- Modify: `tests/Unit/Support/Budgets/BudgetSummaryTest.php`
- Modify: `app/Support/Budgets/BudgetSummary.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Support/Budgets/BudgetSummaryTest.php`:

```php
test('withPockets adds plan to expense and saved and released to execution', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'income', 'monthly_plan' => '5000.00', 'actual' => '4800.00'],
        ['type' => 'expense', 'monthly_plan' => '3000.00', 'actual' => '3200.00'],
    ], planKey: 'monthly_plan');

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => '200.00',
            'saved' => '200.00',
            'released' => '150.00',
            'currency' => ['code' => 'PLN'],
        ],
    ], 'PLN');

    expect($merged['plan']['income'])->toBe('5000.00')
        ->and($merged['plan']['expense'])->toBe('3200.00')
        ->and($merged['plan']['balance'])->toBe('1800.00')
        ->and($merged['execution']['income'])->toBe('4950.00')
        ->and($merged['execution']['expense'])->toBe('3400.00')
        ->and($merged['execution']['balance'])->toBe('1550.00');
});

test('withPockets skips null monthly_plan but still merges execution', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'expense', 'monthly_plan' => '1000.00', 'actual' => '800.00'],
    ], planKey: 'monthly_plan');

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => null,
            'saved' => '100.00',
            'released' => '50.00',
            'currency' => ['code' => 'PLN'],
        ],
    ], 'PLN');

    expect($merged['plan']['expense'])->toBe('1000.00')
        ->and($merged['execution']['expense'])->toBe('900.00')
        ->and($merged['execution']['income'])->toBe('50.00');
});

test('withPockets skips pockets with non-matching currency', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'expense', 'monthly_plan' => '1000.00', 'actual' => '0.00'],
    ], planKey: 'monthly_plan');

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => '500.00',
            'saved' => '200.00',
            'released' => '100.00',
            'currency' => ['code' => 'EUR'],
        ],
    ], 'PLN');

    expect($merged['plan']['expense'])->toBe('1000.00')
        ->and($merged['execution']['expense'])->toBe('0.00')
        ->and($merged['execution']['income'])->toBe('0.00');
});

test('withPockets recalculates expense_percent only and preserves income_percent', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'income', 'monthly_plan' => '1000.00', 'actual' => '500.00'],
        ['type' => 'expense', 'monthly_plan' => '1000.00', 'actual' => '800.00'],
    ], planKey: 'monthly_plan');

    expect($summary['progress']['income_percent'])->toBe(50)
        ->and($summary['progress']['expense_percent'])->toBe(80);

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => '200.00',
            'saved' => '400.00',
            'released' => '100.00',
            'currency' => ['code' => 'PLN'],
        ],
    ], 'PLN');

    expect($merged['progress']['income_percent'])->toBe(50)
        ->and($merged['progress']['expense_percent'])->toBe(67);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetSummaryTest.php --filter=withPockets`  
Expected: FAIL — method `withPockets` not found

- [ ] **Step 3: Implement `withPockets`**

Add to `app/Support/Budgets/BudgetSummary.php`:

```php
/**
 * @param  array{
 *     plan: array{income: string, expense: string, balance: string},
 *     execution: array{income: string, expense: string, balance: string},
 *     progress: array{income_percent: int|null, expense_percent: int|null},
 *     forecast?: array{income: string, expense: string, balance: string}
 * }  $summary
 * @param  list<array{
 *     monthly_plan: ?string,
 *     saved: string,
 *     released: string,
 *     currency: array{code: string}
 * }>  $pocketRows
 * @return array{
 *     plan: array{income: string, expense: string, balance: string},
 *     execution: array{income: string, expense: string, balance: string},
 *     progress: array{income_percent: int|null, expense_percent: int|null},
 *     forecast?: array{income: string, expense: string, balance: string}
 * }
 */
public static function withPockets(array $summary, array $pocketRows, string $summaryCurrencyCode): array
{
    $planExpense = $summary['plan']['expense'];
    $executionIncome = $summary['execution']['income'];
    $executionExpense = $summary['execution']['expense'];

    foreach ($pocketRows as $row) {
        if (($row['currency']['code'] ?? '') !== $summaryCurrencyCode) {
            continue;
        }

        $monthlyPlan = $row['monthly_plan'] ?? null;
        if ($monthlyPlan !== null) {
            $planExpense = bcadd($planExpense, (string) $monthlyPlan, 2);
        }

        $executionExpense = bcadd($executionExpense, (string) ($row['saved'] ?? '0.00'), 2);
        $executionIncome = bcadd($executionIncome, (string) ($row['released'] ?? '0.00'), 2);
    }

    $summary['plan']['expense'] = $planExpense;
    $summary['plan']['balance'] = bcsub($summary['plan']['income'], $planExpense, 2);
    $summary['execution']['income'] = $executionIncome;
    $summary['execution']['expense'] = $executionExpense;
    $summary['execution']['balance'] = bcsub($executionIncome, $executionExpense, 2);
    $summary['progress']['expense_percent'] = BudgetProgress::percent($planExpense, $executionExpense);

    return $summary;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetSummaryTest.php`  
Expected: PASS (all tests in file)

- [ ] **Step 5: Format PHP**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Support/Budgets/BudgetSummary.php tests/Unit/Support/Budgets/BudgetSummaryTest.php
git commit -m "feat(budget): merge pocket totals into monthly summary helper"
```

---

### Task 2: Wire `ListMonthlyBudget` to call `withPockets`

**Files:**
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php:131-136`

- [ ] **Step 1: Reorder and merge in `handle()`**

Replace:

```php
        $this->summary = BudgetSummary::fromRows($this->rows, planKey: 'monthly_plan');
        $this->pocketRows = $this->buildPocketRows($user, $period);
```

With:

```php
        $this->pocketRows = $this->buildPocketRows($user, $period);

        $summary = BudgetSummary::fromRows($this->rows, planKey: 'monthly_plan');
        $this->summary = BudgetSummary::withPockets(
            $summary,
            $this->pocketRows,
            BudgetCurrency::pln()['code'],
        );
```

- [ ] **Step 2: Run existing monthly budget tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`  
Expected: PASS (existing tests still pass — no pockets in most fixtures)

- [ ] **Step 3: Format PHP**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Actions/Budgets/ListMonthlyBudget.php
git commit -m "feat(budget): include pockets in monthly summary aggregation"
```

---

### Task 3: Feature tests for Inertia summary props

**Files:**
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: Write failing feature test — execution**

Append after the existing pocket row test:

```php
test('monthly budget summary includes pocket saved as expense and released as income', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->ordered()
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $expenseCategory->id,
        'year' => 2026,
        'amount' => 3600,
    ]);

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'target_amount' => '6000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '500.00',
    ]);

    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Oszczędności',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $saveTransferId = 'summary-save-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '-200.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('summary-xfer-out', true),
        'transfer_id' => $saveTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '200.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('summary-xfer-in', true),
        'transfer_id' => $saveTransferId,
    ]);

    $releaseTransferId = 'summary-release-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '-150.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('summary-xfer-out-release', true),
        'transfer_id' => $releaseTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '150.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('summary-xfer-in-release', true),
        'transfer_id' => $releaseTransferId,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.plan.expense', '800.00')
        ->where('summary.execution.expense', '200.00')
        ->where('summary.execution.income', '150.00')
        ->where('summary.execution.balance', '-50.00')
    );
});
```

- [ ] **Step 2: Run test to verify it fails (if Task 2 not done) or passes**

Run: `./vendor/bin/sail artisan test --compact --filter="monthly budget summary includes pocket"`  
Expected after Task 2: PASS

- [ ] **Step 3: Write feature test — plan expense**

Append:

```php
test('monthly budget summary plan expense includes pocket monthly plan', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    Pocket::factory()->create([
        'user_id' => $user->id,
        'target_amount' => '6000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '500.00',
    ]);

    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->ordered()
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $expenseCategory->id,
        'year' => 2026,
        'amount' => 3600,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.plan.expense', '800.00')
    );
});
```

- [ ] **Step 4: Run all monthly budget tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`  
Expected: PASS

- [ ] **Step 5: Format PHP**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "test(budget): assert pocket totals in monthly summary props"
```

---

### Task 4: Final verification

- [ ] **Step 1: Run full scoped test suite**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetSummaryTest.php tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: all PASS

- [ ] **Step 2: Update checklist (optional, on merge branch)**

In `.docs/checklist.md` §19 (Kieszenie), add note under budżet miesięczny that summary card includes pocket plan/execution — only when merging `improvement/budget-pocket-summary`.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Plan expense += pocket monthly_plan | Task 1, 3 |
| Execution expense += saved | Task 1, 3 |
| Execution income += released | Task 1, 3 |
| Balance recalculated | Task 1 |
| Expense progress recalculated | Task 1 |
| Income progress unchanged (P&L only) | Task 1 |
| Currency filter PLN | Task 1 |
| ListMonthlyBudget wiring | Task 2 |
| No frontend changes | — (nothing to do) |
| Monthly only | Task 2 (yearly untouched) |

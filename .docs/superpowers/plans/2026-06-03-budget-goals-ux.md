# Budget UX refactor + savings goals — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move P&L plan editing to Budget screens, simplify Categories to a label catalog, and add savings **Goals (cele)** with per-goal tracking on transfers (flow A) and a goals section in the monthly budget.

**Architecture:** New domain `Goals` (Variant A — mirror `Categories` estimate patterns). Add nullable `goal_id` on `transactions`. Refactor `ListMonthlyBudget` to expose `goal_rows` instead of `transfers_summary`. Reuse existing `categories.estimates.*` routes for P&L plans from `budget/Yearly.vue` and `budget/Monthly.vue` (no backend move — UI only). Goal metrics computed from transfer legs on `Account.type = Savings`.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-03-budget-goals-ux-design.md`

**Suggested branch:** `feature/budget-goals-ux` (from `feat/categories` or `develop`)

---

## File map (overview)

| Action | Path |
|--------|------|
| Modify | `.docs/prd.md` — FR-G1–G5, FR-UX1, §7 nav, §5 Goal models |
| Create | `database/migrations/2026_06_04_100000_create_goals_tables.php` |
| Create | `database/migrations/2026_06_04_110000_add_goal_id_to_transactions.php` |
| Create | `app/Models/Goal.php`, `GoalAnnualEstimate.php`, `GoalMonthlyEstimate.php` |
| Create | `database/factories/GoalFactory.php` |
| Create | `app/Policies/GoalPolicy.php` |
| Create | `app/Actions/Goals/*` (List, Store, Update, Delete, SaveAnnualEstimate, SaveMonthlyEstimate) |
| Create | `app/Support/Goals/GoalPlanAmount.php` (mirror `CategoryPlanAmount`) |
| Create | `app/Support/Goals/GoalTransactionMetrics.php` (saved/released/linked expenses) |
| Create | `app/Http/Controllers/Goals/GoalController.php` |
| Create | `app/Http/Requests/Goals/*` |
| Create | `app/Http/Resources/Goals/GoalResource.php` |
| Create | `routes/goals.php` |
| Modify | `routes/web.php` — `require goals.php` |
| Modify | `app/Models/Transaction.php` — `goal_id`, relation |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` — `getGoalRows()`, remove `getTransfersSummary()` |
| Modify | `app/Http/Controllers/Budgets/BudgetController.php` |
| Modify | `app/Actions/Transfers/CreateTransfer.php` |
| Modify | `app/Http/Requests/Transfers/StoreTransferRequest.php` |
| Modify | `app/Http/Requests/Concerns/ValidatesGoalId.php` (new concern) |
| Modify | `app/Http/Requests/Transactions/StoreTransactionRequest.php`, `UpdateTransactionRequest.php` |
| Modify | `app/Actions/Transactions/StoreTransaction.php`, `UpdateTransaction.php` |
| Modify | `resources/js/pages/categories/Index.vue` — remove year + estimate inputs |
| Modify | `resources/js/pages/budget/Yearly.vue` — editable annual plan |
| Modify | `resources/js/pages/budget/Monthly.vue` — goals section, remove transfers_summary |
| Create | `resources/js/pages/goals/Index.vue` |
| Modify | `resources/js/pages/transfers/Create.vue` — conditional goal select |
| Modify | `resources/js/pages/transactions/Create.vue`, `Edit.vue` — optional goal |
| Modify | `resources/js/components/AppSidebar.vue` |
| Modify | `resources/js/locales/pl.json`, `en.json` |
| Create | `tests/Feature/Goals/*` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Modify | `tests/Feature/Transfers/*` |
| Modify | `.docs/checklist.md` — section 19 |

---

### Task 0: Update PRD

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1:** Add dictionary entry **Cel (goal)** in §1.
- [ ] **Step 2:** Extend §5 with `Goal`, `GoalAnnualEstimate`, `GoalMonthlyEstimate`, `Transaction.goal_id`.
- [ ] **Step 3:** Add §6.8 (or extend §6.7) with FR-G1–FR-G5, FR-UX1 from spec; update §7 navigation (Cele in sidebar); update FR index.
- [ ] **Step 4:** Adjust FR-C5 monthly budget text: per-goal section replaces single transfers block.

---

### Task 1: Goals migrations and models

**Files:**
- Create: `database/migrations/2026_06_04_100000_create_goals_tables.php`
- Create: `app/Models/Goal.php`, `GoalAnnualEstimate.php`, `GoalMonthlyEstimate.php`
- Create: `database/factories/GoalFactory.php`

- [ ] **Step 1: Write migration**

```php
// goals: id, user_id, name, sort_order (int), timestamps
// goal_annual_estimates: goal_id, year (smallint), amount (decimal 12,2 nullable), unique(goal_id, year)
// goal_monthly_estimates: goal_id, year, month (tinyint 1-12), amount nullable, unique(goal_id, year, month)
```

- [ ] **Step 2: Models**

`Goal` — `belongsTo User`, `hasMany` estimates, scope `forUser($userId)`, `ordered()`.

`GoalAnnualEstimate` / `GoalMonthlyEstimate` — mirror category estimate models (`$fillable`, casts).

- [ ] **Step 3: Factory**

```php
GoalFactory::definition(): ['user_id' => User::factory(), 'name' => fake()->word(), 'sort_order' => 10]
```

- [ ] **Step 4: Run migration**

Run: `./vendor/bin/sail artisan migrate`

Expected: tables `goals`, `goal_annual_estimates`, `goal_monthly_estimates` created.

---

### Task 2: Goal policy + authorization (TDD)

**Files:**
- Create: `app/Policies/GoalPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` or rely on auto-discovery
- Create: `tests/Feature/Goals/GoalAuthorizationTest.php`

- [ ] **Step 1: Write failing test**

```php
test('user cannot update another users goal', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->patch(route('goals.update', $goal), ['name' => 'Hacked'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalAuthorizationTest.php`  
Expected: FAIL (route or 403)

- [ ] **Step 3: Implement `GoalPolicy`** — `viewAny`, `view`, `create`, `update`, `delete` scoped to `user_id`.

- [ ] **Step 4: Re-run test** — PASS

---

### Task 3: Goals CRUD backend (TDD)

**Files:**
- Create: `app/Actions/Goals/ListGoals.php`, `StoreGoal.php`, `UpdateGoal.php`, `DeleteGoal.php`
- Create: `app/Http/Controllers/Goals/GoalController.php`
- Create: `app/Http/Requests/Goals/StoreGoalRequest.php`, `UpdateGoalRequest.php`
- Create: `app/Http/Resources/Goals/GoalResource.php`
- Create: `routes/goals.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Goals/GoalCrudTest.php`

- [ ] **Step 1: Tests**

```php
test('user can create list update and delete goal without transactions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), ['name' => 'Wakacje'])->assertRedirect();
    $goal = Goal::where('user_id', $user->id)->where('name', 'Wakacje')->first();
    expect($goal)->not->toBeNull();

    $this->actingAs($user)->patch(route('goals.update', $goal), ['name' => 'Wakacje 2026'])->assertRedirect();
    expect($goal->fresh()->name)->toBe('Wakacje 2026');

    $this->actingAs($user)->delete(route('goals.destroy', $goal))->assertRedirect();
    expect(Goal::find($goal->id))->toBeNull();
});

test('cannot delete goal with linked transactions', function () {
    // create goal + transaction with goal_id after Task 5 — mark skipped until then or use raw DB insert
});
```

- [ ] **Step 2: Implement** — mirror `CategoryController` / `CategoryCrudTest` patterns; `DeleteGoal` blocks when `transactions()->exists()`.

- [ ] **Step 3: Routes**

```php
// routes/goals.php
Route::middleware('auth')->group(function () {
    Route::resource('goals', GoalController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('goals/{goal}/estimates/annual', [GoalController::class, 'saveAnnualEstimate'])->name('goals.estimates.annual');
    Route::patch('goals/{goal}/estimates/monthly', [GoalController::class, 'saveMonthlyEstimate'])->name('goals.estimates.monthly');
});
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Goals/GoalCrudTest.php`

---

### Task 4: Goal estimates (TDD)

**Files:**
- Create: `app/Actions/Goals/SaveAnnualEstimate.php`, `SaveMonthlyEstimate.php`
- Create: `app/Http/Requests/Goals/SaveAnnualEstimateRequest.php`, `SaveMonthlyEstimateRequest.php`
- Create: `app/Support/Goals/GoalPlanAmount.php`
- Extend: `GoalController` — estimate actions
- Create: `tests/Feature/Goals/GoalEstimatesTest.php`

- [ ] **Step 1: Test annual upsert**

```php
test('goal annual estimate upsert', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->patch(route('goals.estimates.annual', $goal), [
        'year' => 2026,
        'amount' => '2400',
    ])->assertRedirect();

    expect(GoalAnnualEstimate::where('goal_id', $goal->id)->where('year', 2026)->value('amount'))
        ->toBe('2400.00');
});
```

- [ ] **Step 2: Implement** — copy structure from `SaveAnnualEstimate` (Categories); telemetry `goal_estimate_annual_saved`, `goal_estimate_monthly_saved`.

- [ ] **Step 3: `GoalPlanAmount::monthly()` / `annual()`** — identical logic to `CategoryPlanAmount`.

- [ ] **Step 4: Run tests** — PASS

---

### Task 5: Add `goal_id` to transactions

**Files:**
- Create: `database/migrations/2026_06_04_110000_add_goal_id_to_transactions.php`
- Modify: `app/Models/Transaction.php`
- Modify: `database/factories/TransactionFactory.php`

- [ ] **Step 1: Migration**

```php
$table->foreignId('goal_id')->nullable()->after('category_id')->constrained('goals')->nullOnDelete();
$table->index(['user_id', 'goal_id', 'booked_at']); // budget aggregations
```

- [ ] **Step 2: Model** — `goal()` belongsTo; add to `$fillable`.

- [ ] **Step 3: Migrate**

Run: `./vendor/bin/sail artisan migrate`

- [ ] **Step 4: Complete delete-block test** in `GoalCrudTest` with factory transaction having `goal_id`.

---

### Task 6: Transfer goal validation (TDD)

**Files:**
- Create: `app/Http/Requests/Concerns/ValidatesGoalId.php`
- Modify: `app/Http/Requests/Transfers/StoreTransferRequest.php`
- Modify: `app/Actions/Transfers/CreateTransfer.php`
- Modify: `app/Http/Controllers/Transfers/TransferController.php` — pass `goals` list
- Create: `tests/Feature/Transfers/TransferGoalTest.php`

- [ ] **Step 1: Test — savings transfer requires goal**

```php
test('transfer to savings account requires goal_id', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);
    // ... create ROR + Savings accounts, goal, default category ...

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'date' => '01-03-2026',
        'amount' => '200',
        'category_id' => $savingsCategoryId,
        // no goal_id
    ])->assertSessionHasErrors('goal_id');
});
```

- [ ] **Step 2: Test — both legs share goal_id**

```php
test('transfer persists same goal_id on both legs', function () {
    // ... valid payload with goal_id ...
    $withdraw = Transaction::where('transfer_id', $transferId)->where('amount', '<', 0)->first();
    $deposit = Transaction::where('transfer_id', $transferId)->where('amount', '>', 0)->first();
    expect($withdraw->goal_id)->toBe($goal->id);
    expect($deposit->goal_id)->toBe($goal->id);
});
```

- [ ] **Step 3: `ValidatesGoalId` concern**

Rules:
- Load `from_account_id`, `to_account_id`; if either account `type === Savings` → `goal_id` required, must exist for user.
- If neither is Savings → `goal_id` must be absent (prohibited).
- Custom rule: both legs same goal (enforced in Action by single field).

- [ ] **Step 4: `CreateTransfer`** — add `'goal_id' => $validated['goal_id'] ?? null` on both `Transaction::create()` arrays.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferGoalTest.php`

---

### Task 7: Optional goal on manual transactions (TDD)

**Files:**
- Modify: `StoreTransactionRequest`, `UpdateTransactionRequest`
- Modify: `StoreTransaction`, `UpdateTransaction`
- Modify: `TransactionController` create/edit — pass goals
- Create: `tests/Feature/Transactions/TransactionGoalTest.php`

- [ ] **Step 1: Test optional goal on expense**

```php
test('expense can optionally reference a goal', function () {
    // store with goal_id => assert saved
});

test('goal_id must belong to user', function () {
    // 422
});
```

- [ ] **Step 2: Validation** — `goal_id` nullable; `Rule::exists('goals', 'id')->where('user_id', ...)`.

- [ ] **Step 3: Persist in Store/Update actions.**

- [ ] **Step 4: Run tests**

---

### Task 8: Goal metrics + monthly budget refactor (TDD)

**Files:**
- Create: `app/Support/Goals/GoalTransactionMetrics.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `app/Http/Controllers/Budgets/BudgetController.php`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: `GoalTransactionMetrics`**

```php
final class GoalTransactionMetrics
{
    /** @return array{saved: string, released: string, balance: string, linked_expenses: string} */
    public static function forMonth(User $user, Goal $goal, BudgetPeriod $period): array
    {
        // saved: SUM(amount) where goal_id, account.type=Savings, amount>0, in period, transfer_id NOT NULL
        // released: SUM(ABS(amount)) where goal_id, account.type=Savings, amount<0, in period, transfer_id NOT NULL
        // linked_expenses: SUM(ABS(amount)) where goal_id, transfer_id IS NULL, amount<0, in period
        // balance: saved - released (month scope)
    }
}
```

- [ ] **Step 2: Replace `buildTransfersSummary()` in `ListMonthlyBudget`**

- Load all user goals (ordered).
- Load annual/monthly estimates keyed by goal_id.
- For each goal build row:

```php
[
    'goal_id' => ...,
    'name' => ...,
    'monthly_plan' => GoalPlanAmount::monthly(...),
    'saved' => ...,
    'released' => ...,
    'balance' => ...,
    'linked_expenses' => ...,
]
```

- Remove `getTransfersSummary()`; add `getGoalRows()`.

- [ ] **Step 3: Update test** — replace `transfers_summary` assertions with `goal_rows`:

```php
test('monthly budget goal row tracks save and release on savings account', function () {
    // flow A: transfer 200 to savings with goal, transfer 150 back
    $response->assertInertia(fn ($page) => $page
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['saved'] === '200.00')
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['released'] === '150.00')
    );
});
```

- [ ] **Step 4: Controller** — pass `'goal_rows' => $listMonthlyBudget->getGoalRows()`; drop `transfers_summary`.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`

---

### Task 9: Data migration — default goal from Oszczędności estimate (optional seed)

**Files:**
- Create: `app/Actions/Goals/MigrateLegacySavingsEstimate.php` (or inline in migration)
- Modify: migration `2026_06_04_110000_add_goal_id_to_transactions.php` data step **or** one-time command

- [ ] **Step 1:** For each user with `CategoryAnnualEstimate` on system „Oszczędności” for year Y:
  - Create goal „Oszczędności ogólne” if user has zero goals.
  - Copy annual + monthly estimates to goal estimates.
  - Do **not** auto-assign `goal_id` on past transfers (leave null; metrics ignore).

- [ ] **Step 2: Test** — user with Oszczędności estimate 5000 gets default goal with same annual amount.

---

### Task 10: Frontend — Categories strip estimates

**Files:**
- Modify: `resources/js/pages/categories/Index.vue`

- [ ] **Step 1:** Remove `year` prop usage, year selector, `saveAnnualEstimate`, estimate inputs.
- [ ] **Step 2:** Keep link to budget: `route('budget.monthly')`.
- [ ] **Step 3:** Update `CategoryController::index` — stop passing year to estimates (ListCategories may still load without year filter on estimates).

- [ ] **Step 4:** Adjust `ListCategories` / `CategoryResource` — omit `annual_estimate_amount` from categories index payload (or leave null).

---

### Task 11: Frontend — Budget yearly editable plans

**Files:**
- Modify: `resources/js/pages/budget/Yearly.vue`

- [ ] **Step 1:** Copy annual estimate input pattern from old `categories/Index.vue`:

```typescript
function saveAnnualEstimate(row: BudgetRow, rawValue: string) {
    router.patch(route('categories.estimates.annual', row.category_id), {
        year: props.year,
        amount: trimmed === '' ? null : trimmed.replace(',', '.'),
    }, { preserveScroll: true });
}
```

- [ ] **Step 2:** Replace read-only plan cell with `Input` + `@blur` (same UX as categories had).

- [ ] **Step 3:** Add link „Zarządzaj kategoriami” → `route('categories.index')`.

---

### Task 12: Frontend — Budget monthly goals section

**Files:**
- Modify: `resources/js/pages/budget/Monthly.vue`

- [ ] **Step 1:** Replace `transfers_summary` prop with `goal_rows` type:

```typescript
type GoalRow = {
    goal_id: number;
    name: string;
    monthly_plan: string | null;
    saved: string;
    released: string;
    balance: string;
    linked_expenses: string;
};
```

- [ ] **Step 2:** Remove transfers section template; add goals table (columns: Cel, Szacunek, Odłożono, Wypłacono, Saldo, Powiązane wydatki).

- [ ] **Step 3:** Monthly plan edit → `route('goals.estimates.monthly', row.goal_id)`.

- [ ] **Step 4:** Link „Zarządzaj celami” → `route('goals.index')`.

---

### Task 13: Frontend — Goals index page

**Files:**
- Create: `resources/js/pages/goals/Index.vue`

- [ ] **Step 1:** Mirror `categories/Index.vue` structure without type field:
  - Add goal form (name only)
  - List with rename, delete, reorder (optional v1: skip reorder if YAGNI)
  - Year selector + annual estimate per goal (same blur-save as categories had)

- [ ] **Step 2:** Link to monthly budget.

---

### Task 14: Frontend — Transfer + transaction goal pickers

**Files:**
- Modify: `resources/js/pages/transfers/Create.vue`
- Modify: `app/Http/Controllers/Transfers/TransferController.php`
- Modify: `resources/js/pages/transactions/Create.vue`, `Edit.vue`

- [ ] **Step 1: Transfer** — props: `goals: { id, name }[]`.

```typescript
const involvesSavings = computed(() => {
    const from = accountsById.value.get(form.from_account_id);
    const to = accountsById.value.get(form.to_account_id);
    return from?.type === 'Savings' || to?.type === 'Savings';
});
// v-if="involvesSavings" — required DropdownSelect goal_id
```

- [ ] **Step 2: Transaction forms** — optional goal dropdown (all user goals).

- [ ] **Step 3:** Manual smoke: ROR→Savings transfer requires goal; ROR→ROR hides goal field.

---

### Task 15: Navigation + i18n

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/locales/pl.json`, `en.json`

- [ ] **Step 1:** Add nav item „Cele” → `route('goals.index')`, icon e.g. `Target` from lucide.

- [ ] **Step 2:** Add keys:

```json
"goals": {
  "index": { "title": "Cele", "add": "Dodaj cel", ... },
  "toast": { "created": "...", ... }
},
"budget": {
  "monthly": {
    "goals_section": "Cele oszczędnościowe",
    "saved": "Odłożono",
    "released": "Wypłacono",
    "balance": "Saldo",
    "linked_expenses": "Powiązane wydatki"
  }
}
```

- [ ] **Step 3:** Remove obsolete copy tied to single transfers section if unused.

---

### Task 16: Quality gates + checklist

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent`
- [ ] **Step 2:** `./vendor/bin/sail artisan test --compact` (or filter `Goals`, `Budgets`, `Transfers`)
- [ ] **Step 3:** Update `.docs/checklist.md` — section **19) Cele + UX budżetu**
- [ ] **Step 4:** Smoke: login → `/budget/yearly` edit plan → `/budget/monthly` goals section → `/goals` CRUD → transfer with goal

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| FR-UX1 P&L plans on budget only | 10, 11 |
| Categories sidebar, no amounts | 10 |
| FR-G1 Goals CRUD | 3 |
| FR-G2 Goal estimates | 4, 13 |
| FR-G3 Goal on savings transfers | 6 |
| FR-G4 Optional goal on expense | 7, 14 |
| FR-G5 Goals in monthly budget | 8, 12 |
| Flow A metrics | 8 |
| Sidebar Cele | 15 |
| Replace transfers_summary | 8, 12 |
| Telemetry goal_* | 4 |

---

## Self-review notes

- Yearly goals rollup deferred (spec: optional v1) — only monthly goal table + goals index.
- Import rows: `goal_id` stays null unless future FR; transfers from UI covered in Task 6.
- `EnsureUserCategories` unchanged; no auto-create goals on register (user creates goals explicitly; migration handles legacy Oszczędności estimate).

---

Plan complete and saved to `.docs/superpowers/plans/2026-06-03-budget-goals-ux.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement task-by-task in this session with checkpoints

Which approach?

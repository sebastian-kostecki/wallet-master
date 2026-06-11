# Pocket monthly contribution without target — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users set an optional monthly savings amount on a pocket without a savings target; the amount appears in the monthly budget pocket section and expense plan totals.

**Architecture:** Keep `monthly_contribution` as a standalone optional field when `target_amount` is null (`planning_mode` stays null). Stop clearing `monthly_contribution` in form request `prepareForValidation`. Extend `PocketPlanningProjection::monthlyPlanForBudget()` default branch. Move the monthly field outside the target-only planning block in Create/Edit Vue forms.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-10-pocket-monthly-without-target-design.md`

**Suggested branch:** `improvement/pocket-monthly-without-target`

---

## File map

| Action | Path |
|--------|------|
| Modify | `app/Support/Pockets/PocketPlanningProjection.php` |
| Modify | `app/Http/Requests/Pockets/StorePocketRequest.php` |
| Modify | `app/Http/Requests/Pockets/UpdatePocketRequest.php` |
| Modify | `resources/js/pages/pockets/Create.vue` |
| Modify | `resources/js/pages/pockets/Edit.vue` |
| Modify | `tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php` |
| Modify | `tests/Feature/Pockets/PocketPlanningTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Modify | `.docs/checklist.md` |

---

### Task 1: `monthlyPlanForBudget` — unit tests + implementation

**Files:**
- Modify: `app/Support/Pockets/PocketPlanningProjection.php`
- Modify: `tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php`

- [ ] **Step 1: Write failing unit tests**

Append to `tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php`:

```php
test('monthlyPlanForBudget returns monthly_contribution without target or planning mode', function () {
    $pocket = new Pocket([
        'target_amount' => null,
        'planning_mode' => null,
        'monthly_contribution' => '200.00',
    ]);

    expect(PocketPlanningProjection::monthlyPlanForBudget($pocket, '0.00'))->toBe('200.00');
});

test('monthlyPlanForBudget returns null without target and without monthly contribution', function () {
    $pocket = new Pocket([
        'target_amount' => null,
        'planning_mode' => null,
        'monthly_contribution' => null,
    ]);

    expect(PocketPlanningProjection::monthlyPlanForBudget($pocket, '0.00'))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php`

Expected: FAIL — second new test returns `null` already; first test FAILS returning `null` instead of `'200.00'`.

- [ ] **Step 3: Update `monthlyPlanForBudget`**

In `app/Support/Pockets/PocketPlanningProjection.php`, replace `monthlyPlanForBudget()` with:

```php
public static function monthlyPlanForBudget(Pocket $pocket, string $balance): ?string
{
    return match ($pocket->planning_mode) {
        PocketPlanningMode::Monthly => $pocket->monthly_contribution !== null
            ? (string) $pocket->monthly_contribution
            : null,
        PocketPlanningMode::ByDate => self::recommendedMonthly($pocket, $balance),
        default => $pocket->monthly_contribution !== null
            ? (string) $pocket->monthly_contribution
            : null,
    };
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php`

Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Pockets/PocketPlanningProjection.php tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php
git commit -m "feat(pockets): budget plan from monthly contribution without target"
```

---

### Task 2: Form request validation — stop clearing monthly contribution

**Files:**
- Modify: `app/Http/Requests/Pockets/StorePocketRequest.php`
- Modify: `app/Http/Requests/Pockets/UpdatePocketRequest.php`
- Modify: `tests/Feature/Pockets/PocketPlanningTest.php`

- [ ] **Step 1: Write failing feature tests**

Append to `tests/Feature/Pockets/PocketPlanningTest.php`:

```php
test('pocket without target can store optional monthly contribution', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Bufor',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'monthly_contribution' => '200',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Bufor')->first();

    expect($pocket)->not->toBeNull()
        ->and($pocket->target_amount)->toBeNull()
        ->and($pocket->planning_mode)->toBeNull()
        ->and((string) $pocket->monthly_contribution)->toBe('200.00');
});

test('pocket without target can be stored without monthly contribution', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Luźne',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Luźne')->first();

    expect($pocket)->not->toBeNull()
        ->and($pocket->monthly_contribution)->toBeNull();
});

test('clearing target keeps monthly contribution on update', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'target_amount' => '5000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '250.00',
    ]);

    $this->actingAs($user)->patch(route('pockets.update', $pocket), [
        'target_amount' => '',
        'monthly_contribution' => '250',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pocket->refresh();

    expect($pocket->target_amount)->toBeNull()
        ->and($pocket->planning_mode)->toBeNull()
        ->and((string) $pocket->monthly_contribution)->toBe('250.00');
});
```

Add imports at top of `PocketPlanningTest.php` if missing:

```php
use App\Enums\PocketPlanningMode;
use App\Models\Currency;
use App\Models\Pocket;
use App\Models\User;
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketPlanningTest.php --filter="without target"`

Expected: FAIL — first and third tests fail (monthly_contribution cleared or validation error).

- [ ] **Step 3: Update `StorePocketRequest::prepareForValidation`**

In `app/Http/Requests/Pockets/StorePocketRequest.php`, change the empty-target merge block from:

```php
$this->merge([
    'target_amount' => null,
    'planning_mode' => null,
    'monthly_contribution' => null,
    'target_date' => null,
]);
```

to:

```php
$this->merge([
    'target_amount' => null,
    'planning_mode' => null,
    'target_date' => null,
]);
```

- [ ] **Step 4: Update `UpdatePocketRequest::prepareForValidation`**

In `app/Http/Requests/Pockets/UpdatePocketRequest.php`, change the empty-target merge block from:

```php
$this->merge([
    'target_amount' => null,
    'planning_mode' => null,
    'monthly_contribution' => null,
    'target_date' => null,
]);
```

to:

```php
$this->merge([
    'target_amount' => null,
    'planning_mode' => null,
    'target_date' => null,
]);
```

- [ ] **Step 5: Run feature tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketPlanningTest.php`

Expected: PASS (all tests in file, including existing regression tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Pockets/StorePocketRequest.php app/Http/Requests/Pockets/UpdatePocketRequest.php tests/Feature/Pockets/PocketPlanningTest.php
git commit -m "feat(pockets): allow monthly contribution without savings target"
```

---

### Task 3: Monthly budget integration test

**Files:**
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: Write failing budget test**

Append to `tests/Feature/Budgets/MonthlyBudgetTest.php`:

```php
test('monthly budget shows pocket plan without target when monthly contribution is set', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'target_amount' => null,
        'planning_mode' => null,
        'monthly_contribution' => '500.00',
    ]);

    $response = $this->actingAs($user)->get(route('budget.monthly', ['year' => 2026, 'month' => 3], absolute: false));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['monthly_plan'] === '500.00')
        ->where('summary.plan.expense', fn ($expense) => bccomp((string) $expense, '500.00', 2) >= 0)
    );
});
```

- [ ] **Step 2: Run test to verify it fails (if Task 1 not done) or passes**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php --filter="without target"`

Expected: PASS after Task 1 (if run in order).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "test(budget): pocket monthly plan without savings target"
```

---

### Task 4: Create form — always-visible monthly field

**Files:**
- Modify: `resources/js/pages/pockets/Create.vue`

- [ ] **Step 1: Update `submit()` — keep monthly when no target**

In `resources/js/pages/pockets/Create.vue`, replace the `submit()` no-target branch:

```typescript
if (!hasTarget.value) {
    form.target_amount = '';
    form.monthly_contribution = '';
    form.target_date = '';
}
```

with:

```typescript
if (!hasTarget.value) {
    form.target_amount = '';
    form.target_date = '';
    (form as { planning_mode: 'monthly' | 'by_date' | null }).planning_mode = null;
}
```

- [ ] **Step 2: Move monthly field outside `hasTarget` block**

In the template, **remove** the `FormField` for `monthly_contribution` from inside `<div v-if="hasTarget">` (the block that currently also contains `planning_mode` and `target_date`).

**Insert** this block **after** the `target_amount` `FormField` and **before** the color `FormField`:

```vue
<FormField
    for-id="monthly_contribution"
    :label="t('pockets.fields.monthlyContribution')"
    :error="form.errors.monthly_contribution"
>
    <template #default="{ errorId, hasError }">
        <div class="relative">
            <Input
                id="monthly_contribution"
                v-model="form.monthly_contribution"
                type="text"
                inputmode="decimal"
                class="pr-10"
                :aria-invalid="hasError ? true : undefined"
                :aria-describedby="hasError ? errorId : undefined"
            />
            <span
                v-if="selectedCurrency?.symbol"
                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
            >
                {{ selectedCurrency.symbol }}
            </span>
        </div>
    </template>
</FormField>
```

Inside `hasTarget`, keep only `planning_mode` and:
- `monthly_contribution` field when `form.planning_mode === 'monthly'` — **remove duplicate**; with field moved out, the `hasTarget` block contains only `planning_mode` + `target_date` (when `by_date`).

Resulting `hasTarget` block:

```vue
<div v-if="hasTarget" class="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
    <FormField for-id="planning_mode" :label="t('pockets.fields.planningMode')">
        <SegmentedControl
            id="planning_mode"
            :model-value="form.planning_mode"
            :options="planningModeOptions"
            :aria-label="t('pockets.fields.planningMode')"
            @update:model-value="(value) => (form.planning_mode = value as 'monthly' | 'by_date')"
        />
    </FormField>

    <FormField v-if="form.planning_mode === 'by_date'" for-id="target_date" :label="t('pockets.fields.targetDate')" :error="form.errors.target_date">
        <Input id="target_date" v-model="form.target_date" type="date" />
    </FormField>
</div>
```

When target + `monthly` mode, the always-visible `monthly_contribution` field satisfies the required contribution (same input, no duplicate).

- [ ] **Step 3: Manual smoke check**

Run: `./vendor/bin/sail npm run build` (or confirm dev server running).

Visit create pocket page; confirm monthly field visible without target amount.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/pockets/Create.vue
git commit -m "feat(pockets): show monthly contribution field without target on create"
```

---

### Task 5: Edit form — always-visible monthly field

**Files:**
- Modify: `resources/js/pages/pockets/Edit.vue`

- [ ] **Step 1: Update `submit()` — keep monthly when no target**

In `resources/js/pages/pockets/Edit.vue`, replace the no-target branch in `submit()`:

```typescript
if (!hasTarget.value) {
    form.target_amount = '';
    form.monthly_contribution = '';
    form.target_date = '';
}
```

with:

```typescript
if (!hasTarget.value) {
    form.target_amount = '';
    form.target_date = '';
    (form as { planning_mode: 'monthly' | 'by_date' | null }).planning_mode = null;
}
```

- [ ] **Step 2: Move monthly field outside `hasTarget` block**

Same template change as Task 4 Step 2: place `monthly_contribution` `FormField` after `target_amount`, simplify `hasTarget` block to `planning_mode` + conditional `target_date` only.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/pockets/Edit.vue
git commit -m "feat(pockets): show monthly contribution field without target on edit"
```

---

### Task 6: Final verification and docs

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Run Pint on dirty PHP files**

Run: `vendor/bin/pint --dirty --format agent`

Expected: no formatting issues (or auto-fixed).

- [ ] **Step 2: Run scoped test suite**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php tests/Feature/Pockets/PocketPlanningTest.php tests/Feature/Budgets/MonthlyBudgetTest.php --filter="without target|monthlyPlanForBudget|pocket with target"
```

Run full pocket + budget sanity:

```bash
./vendor/bin/sail artisan test --compact --filter=Pocket
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: all PASS.

- [ ] **Step 3: Update checklist**

In `.docs/checklist.md`, under section **19) Kieszenie (Pockets)**, add a completed item:

```markdown
- [x] Miesięczna kwota odkładania bez celu — widoczna w budżecie (`2026-06-10-pocket-monthly-without-target-design.md`)
```

- [ ] **Step 4: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: checklist — pocket monthly plan without target"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Optional `monthly_contribution` without target | Task 2 |
| `planning_mode` null without target | Task 2, 4, 5 |
| `monthlyPlanForBudget` default branch | Task 1 |
| Budget `pocket_rows[].monthly_plan` | Task 1, 3 |
| Summary expense includes pocket plan | Task 3 |
| Create/Edit form layout | Task 4, 5 |
| Index unchanged | No task (no files) |
| Regression: target + monthly/by_date rules | Task 2 (existing tests) |
| Projections unchanged | No code change (Task 1 only touches budget plan) |

## Execution handoff

Plan complete and saved to `.docs/superpowers/plans/2026-06-10-pocket-monthly-without-target.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** — implement tasks in this session with checkpoints

Which approach?

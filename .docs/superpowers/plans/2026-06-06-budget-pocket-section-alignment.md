# Budget monthly — pocket section alignment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the monthly budget pockets table with income/expense sections — shared grid, combined movement column, monthly progress — and trim unused pocket row props.

**Architecture:** Backend changes only in `ListMonthlyBudget::buildPocketRows()` (progress semantics + payload trim). Frontend extracts inline pockets table into `BudgetPocketSection` + `BudgetPocketMovementCell`, reusing `BudgetTableColgroup` and `BudgetProgressCell`. No route, controller, or summary aggregation changes.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia v2, Vue 3, Tailwind v3, Pest 4, Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-06-budget-pocket-section-alignment-design.md`

**Suggested branch:** `improvement/budget-pocket-section-alignment` (from `develop`)

---

## File map

| Action | Path |
|--------|------|
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Create | `resources/js/components/budget/BudgetPocketMovementCell.vue` |
| Create | `resources/js/components/budget/BudgetPocketSection.vue` |
| Modify | `resources/js/components/budget/BudgetTableColgroup.vue` |
| Modify | `resources/js/pages/budget/Monthly.vue` |
| Modify | `resources/js/locales/pl.json` |
| Modify | `resources/js/locales/en.json` |
| Modify | `.docs/checklist.md` (reconcile after merge) |

---

### Task 1: Backend — monthly pocket progress + trimmed payload

**Files:**
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`

- [ ] **Step 1: Update the failing test expectations**

In `tests/Feature/Budgets/MonthlyBudgetTest.php`, replace the test `monthly budget pocket row tracks save and release on savings account` assertions. Remove `balance` assertion; add monthly progress and absent keys:

```php
test('monthly budget pocket row tracks save and release on savings account', function () {
    // ... existing setup unchanged ...

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['monthly_plan'] === '500.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['saved'] === '200.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['released'] === '150.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['progress_percent'] === 40)
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['currency']['code'] === 'PLN')
        ->where('pocket_rows', fn ($rows) => ! array_key_exists('balance', collect($rows)->firstWhere('pocket_id', $pocket->id)))
        ->where('pocket_rows', fn ($rows) => ! array_key_exists('balance_cumulative', collect($rows)->firstWhere('pocket_id', $pocket->id)))
        ->where('pocket_rows', fn ($rows) => ! array_key_exists('target_amount', collect($rows)->firstWhere('pocket_id', $pocket->id)))
    );
});
```

Add a new test after it:

```php
test('monthly budget pocket progress uses saved vs plan not cumulative target', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'target_amount' => '5000.00',
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

    // Prior-month save — builds cumulative balance without affecting March progress
    $priorTransferId = 'prior-save-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-02-10',
        'booked_at' => '2026-02-10',
        'amount' => '-3000.00',
        'type' => TransactionType::Transfer,
        'description' => 'Prior save',
        'normalized_description' => 'prior save',
        'dedupe_hash' => md5('prior-xfer-out', true),
        'transfer_id' => $priorTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-02-10',
        'booked_at' => '2026-02-10',
        'amount' => '3000.00',
        'type' => TransactionType::Transfer,
        'description' => 'Prior save',
        'normalized_description' => 'prior save',
        'dedupe_hash' => md5('prior-xfer-in', true),
        'transfer_id' => $priorTransferId,
    ]);

    $marchTransferId = 'march-save-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '-250.00',
        'type' => TransactionType::Transfer,
        'description' => 'March save',
        'normalized_description' => 'march save',
        'dedupe_hash' => md5('march-xfer-out', true),
        'transfer_id' => $marchTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '250.00',
        'type' => TransactionType::Transfer,
        'description' => 'March save',
        'normalized_description' => 'march save',
        'dedupe_hash' => md5('march-xfer-in', true),
        'transfer_id' => $marchTransferId,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['saved'] === '250.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['progress_percent'] === 50)
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php --filter="pocket row tracks save|pocket progress uses saved"`

Expected: FAIL — `progress_percent` still reflects cumulative target (e.g. 64) or `balance` key still present.

- [ ] **Step 3: Implement backend changes**

In `app/Actions/Budgets/ListMonthlyBudget.php`:

1. Add import: `use App\Support\Budgets\BudgetProgress;`
2. In `buildPocketRows()`, compute plan once and use monthly progress:

```php
$monthlyPlan = PocketPlanningProjection::monthlyPlanForBudget($pocket, $cumulative['balance']);

$rows[] = [
    'pocket_id' => $pocket->id,
    'name' => $pocket->name,
    'icon' => $pocket->icon,
    'color' => $pocket->color,
    'monthly_plan' => $monthlyPlan,
    'saved' => $metrics['saved'],
    'released' => $metrics['released'],
    'progress_percent' => BudgetProgress::percent($monthlyPlan, $metrics['saved']),
    'currency' => [
        'code' => $pocket->currency->code,
        'symbol' => $pocket->currency->symbol,
        'precision' => $pocket->currency->precision,
    ],
];
```

Remove from row: `balance`, `balance_cumulative`, `target_amount`.

Remove unused `$targetAmount` variable if no longer referenced.

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php`

Expected: PASS (all tests in file).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Budgets/ListMonthlyBudget.php tests/Feature/Budgets/MonthlyBudgetTest.php
git commit -m "feat(budget): monthly pocket progress uses saved vs plan"
```

---

### Task 2: i18n — movement column header

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add locale keys**

In `resources/js/locales/pl.json`, under `budget.monthly`, add after `"released"`:

```json
"movement": "Ruch w miesiącu",
```

In `resources/js/locales/en.json`, under `budget.monthly`, add after `"released"`:

```json
"movement": "Monthly movement",
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "i18n: add budget monthly pocket movement column label"
```

---

### Task 3: `BudgetTableColgroup` — pocket layout

**Files:**
- Modify: `resources/js/components/budget/BudgetTableColgroup.vue`

- [ ] **Step 1: Extend layout prop and add pocket colgroup**

Update `defineProps` type:

```ts
layout: 'summary' | 'category' | 'pocket';
```

Add a new branch before the final `v-else` (yearly category):

```vue
<colgroup v-else-if="layout === 'pocket' && period === 'monthly'">
    <col class="budget-col-label" />
    <col class="budget-col-plan" />
    <col class="budget-col-amount" />
    <col class="budget-col-progress" />
</colgroup>
```

Pocket monthly uses the same 4-column widths as category monthly.

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/BudgetTableColgroup.vue
git commit -m "feat(budget): add pocket layout to BudgetTableColgroup"
```

---

### Task 4: `BudgetPocketMovementCell` component

**Files:**
- Create: `resources/js/components/budget/BudgetPocketMovementCell.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup lang="ts">
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { useI18n } from 'vue-i18n';

defineProps<{
    saved: string;
    released: string;
    currency: CurrencyDisplay;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-1 tabular-nums">
        <div>
            <span class="text-xs text-muted-foreground">+ {{ t('budget.monthly.saved') }}</span>
            <span class="ml-1">{{ formatMoney(saved, currency) }}</span>
        </div>
        <div>
            <span class="text-xs text-muted-foreground">− {{ t('budget.monthly.released') }}</span>
            <span class="ml-1">{{ formatMoney(released, currency) }}</span>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/BudgetPocketMovementCell.vue
git commit -m "feat(budget): add BudgetPocketMovementCell for pocket saved/released"
```

---

### Task 5: `BudgetPocketSection` component

**Files:**
- Create: `resources/js/components/budget/BudgetPocketSection.vue`

- [ ] **Step 1: Create the section component**

```vue
<script setup lang="ts">
import BudgetPocketMovementCell from '@/components/budget/BudgetPocketMovementCell.vue';
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import BudgetTableColgroup from '@/components/budget/BudgetTableColgroup.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

type PocketRow = {
    pocket_id: number;
    name: string;
    icon: string;
    color: string;
    monthly_plan: string | null;
    saved: string;
    released: string;
    progress_percent: number | null;
    currency: CurrencyDisplay;
};

defineProps<{
    rows: PocketRow[];
}>();

const { t } = useI18n();
</script>

<template>
    <section
        v-if="rows.length > 0"
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ t('budget.monthly.pockets_section') }}</h2>
            <Button variant="link" class="h-auto p-0" as-child>
                <Link :href="route('pockets.index')">{{ t('budget.monthly.manage_pockets') }}</Link>
            </Button>
        </div>
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="pocket" period="monthly" />
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4">{{ t('pockets.index.fields.name') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.monthly.plan') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.monthly.movement') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.pocket_id"
                        class="border-b border-sidebar-border/40"
                    >
                        <td class="py-2 pr-4">
                            <PocketBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
                        </td>
                        <td class="py-2 pr-4 tabular-nums">
                            {{ formatMoney(row.monthly_plan, row.currency) }}
                        </td>
                        <td class="py-2 pr-4">
                            <BudgetPocketMovementCell
                                :saved="row.saved"
                                :released="row.released"
                                :currency="row.currency"
                            />
                        </td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="row.progress_percent" category-type="expense" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/budget/BudgetPocketSection.vue
git commit -m "feat(budget): add BudgetPocketSection aligned with P&L grid"
```

---

### Task 6: Wire up `Monthly.vue`

**Files:**
- Modify: `resources/js/pages/budget/Monthly.vue`

- [ ] **Step 1: Simplify types and replace inline section**

1. Add import:

```ts
import BudgetPocketSection from '@/components/budget/BudgetPocketSection.vue';
```

2. Remove imports no longer needed: `PocketBadge`, `formatMoney` (only if unused elsewhere — `formatMoney` is unused after change, remove it).

3. Trim `PocketRow` type:

```ts
type PocketRow = {
    pocket_id: number;
    name: string;
    icon: string;
    color: string;
    monthly_plan: string | null;
    saved: string;
    released: string;
    progress_percent: number | null;
    currency: CurrencyDisplay;
};
```

4. Replace the entire inline `<section class="rounded-xl...">` pockets block (lines ~161–198) with:

```vue
<BudgetPocketSection :rows="pocket_rows" />
```

Keep `.budget-page` CSS vars in `<style scoped>` — they are still used by child tables via inheritance.

- [ ] **Step 2: Run frontend lint (if configured)**

Run: `./vendor/bin/sail npm run lint` (or skip if not in CI scope; at minimum verify `npm run build` compiles).

Optional quick check: `./vendor/bin/sail npm run build`

Expected: build succeeds without Vue/TS errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/budget/Monthly.vue
git commit -m "feat(budget): use BudgetPocketSection on monthly view"
```

---

### Task 7: Verification and checklist

**Files:**
- Modify: `.docs/checklist.md` (on branch before merge)

- [ ] **Step 1: Run full budget test suite**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/`

Expected: PASS

- [ ] **Step 2: Manual smoke check**

1. Open `/budget/monthly` with pockets — Plan column aligns with income/expense tables.
2. Movement cell shows +odłożono / −wypłacono lines.
3. Progress badge reflects saved/plan (expense color semantics).
4. No balance or target subtext under plan.
5. User with zero pockets — section absent.
6. „Zarządzaj kieszeniami” link works.

- [ ] **Step 3: Update checklist**

In `.docs/checklist.md`, under budget pockets UX item, add note that pocket section alignment is done (or add `[x]` sub-item if a `[plan]` entry exists for this work).

- [ ] **Step 4: Final commit (if checklist updated)**

```bash
git add .docs/checklist.md
git commit -m "docs: reconcile checklist for budget pocket section alignment"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| 4-column grid aligned with P&L | Task 3, 5, 6 |
| Movement cell stacked saved/released | Task 4, 5 |
| Monthly balance hidden | Task 5 (no balance column) |
| Progress = saved vs plan | Task 1 |
| No cumulative target subtext | Task 5, 6 |
| Read-only plan | Task 5 (no EditableEstimateCell) |
| Hide section when empty | Task 5 (`v-if`) |
| Manage pockets link | Task 5 |
| Trim API payload | Task 1 |
| i18n movement column | Task 2 |
| Tests | Task 1, 7 |

## Out of scope (confirmed)

- Yearly budget changes
- `BudgetSummary::withPockets()` changes
- Inline pocket plan editing

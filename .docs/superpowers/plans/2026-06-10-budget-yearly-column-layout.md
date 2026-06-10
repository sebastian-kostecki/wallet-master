# Budget yearly — column order, forecast styling, balance colors — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorder yearly budget columns to Plan → Prognoza → Wykonanie → Postęp, mute the forecast column, and color balance cells green/red/neutral by sign.

**Architecture:** Frontend-only change in shared budget table components. Add `signedMoneyClass()` next to `formatMoney()` for reusable signed-color Tailwind classes. Reorder `<col>`, `<th>`, and `<td>` in yearly layouts only; monthly view unchanged.

**Tech Stack:** Vue 3, TypeScript, Tailwind CSS 3, Inertia v2, Pest 4 (existing backend tests), Sail for PHP tests.

**Spec:** `.docs/superpowers/specs/2026-06-10-budget-yearly-column-layout-design.md`  
**Reference (signed colors):** `resources/js/pages/transactions/Index.vue` (`text-emerald-600` / `text-rose-600`)  
**Suggested branch:** `improvement/budget-yearly-column-layout` (from `develop`)

---

## File map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/lib/formatMoney.ts` | Add `signedMoneyClass()` helper |
| Modify | `resources/js/components/budget/BudgetTableColgroup.vue` | Reorder yearly `<col>` elements |
| Modify | `resources/js/components/budget/BudgetSummaryCard.vue` | Column order, muted forecast, signed balance |
| Modify | `resources/js/components/budget/BudgetCategorySection.vue` | Column order, muted forecast |
| Unchanged | `resources/js/pages/budget/Yearly.vue` | CSS column width vars still valid |
| Unchanged | `resources/js/pages/budget/Monthly.vue` | No forecast column |

**Note:** Project has no JS unit-test runner (no Vitest/Jest). `signedMoneyClass` is verified via ESLint + manual browser check. Existing `YearlyBudgetTest` confirms backend payload unchanged.

---

### Task 1: Add `signedMoneyClass` helper

**Files:**
- Modify: `resources/js/lib/formatMoney.ts`

- [ ] **Step 1: Add the helper below `formatMoney`**

Append to `resources/js/lib/formatMoney.ts`:

```ts
export function signedMoneyClass(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const parsed = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));

    if (Number.isNaN(parsed) || parsed === 0) {
        return '';
    }

    if (parsed > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    return 'text-rose-600 dark:text-rose-400';
}
```

- [ ] **Step 2: Run ESLint on the file**

```bash
npm run lint -- resources/js/lib/formatMoney.ts
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/formatMoney.ts
git commit -m "$(cat <<'EOF'
feat(budget): add signedMoneyClass helper for balance coloring

Parse monetary strings and return emerald/rose Tailwind classes for positive/negative values.
EOF
)"
```

---

### Task 2: Reorder yearly colgroup columns

**Files:**
- Modify: `resources/js/components/budget/BudgetTableColgroup.vue`

- [ ] **Step 1: Swap forecast and amount col order for yearly layouts**

In `BudgetTableColgroup.vue`, change both yearly colgroups from `plan → amount → forecast` to `plan → forecast → amount`.

Replace the `summary && period === 'yearly'` block:

```vue
    <colgroup v-else-if="layout === 'summary' && period === 'yearly'">
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-forecast" />
        <col class="budget-col-amount" />
        <col class="budget-col-progress" />
    </colgroup>
```

Replace the final `v-else` block (category yearly):

```vue
    <colgroup v-else>
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-forecast" />
        <col class="budget-col-amount" />
        <col class="budget-col-progress" />
    </colgroup>
```

Monthly colgroups remain unchanged.

- [ ] **Step 2: Run ESLint**

```bash
npm run lint -- resources/js/components/budget/BudgetTableColgroup.vue
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/budget/BudgetTableColgroup.vue
git commit -m "$(cat <<'EOF'
fix(budget): align yearly colgroup order with plan-forecast-execution layout
EOF
)"
```

---

### Task 3: Update BudgetSummaryCard

**Files:**
- Modify: `resources/js/components/budget/BudgetSummaryCard.vue`

- [ ] **Step 1: Import `signedMoneyClass`**

Change the import line:

```ts
import { formatMoney, signedMoneyClass, type CurrencyDisplay } from '@/lib/formatMoney';
```

- [ ] **Step 2: Reorder header columns and mute forecast header**

Replace `<thead>` contents:

```vue
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4" />
                        <th class="py-2 pr-4">{{ t('budget.summary.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4 text-muted-foreground">{{ t('budget.summary.forecast') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.summary.execution') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
```

- [ ] **Step 3: Reorder income row — forecast before execution, muted forecast cell**

Replace the income `<tr>` body cells (after the `<th>` label):

```vue
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.income, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 pr-4 tabular-nums text-muted-foreground">
                            {{ formatMoney(summary.forecast.income, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.execution.income, currency) }}</td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="summary.progress.income_percent" category-type="income" />
                        </td>
```

- [ ] **Step 4: Reorder expense row — same pattern**

Replace the expense `<tr>` body cells:

```vue
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.expense, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 pr-4 tabular-nums text-muted-foreground">
                            {{ formatMoney(summary.forecast.expense, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.execution.expense, currency) }}</td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="summary.progress.expense_percent" category-type="expense" />
                        </td>
```

- [ ] **Step 5: Reorder balance row and apply signed colors**

Replace the balance `<tr>` body cells:

```vue
                        <td class="py-2 pr-4 tabular-nums" :class="signedMoneyClass(summary.plan.balance)">
                            {{ formatMoney(summary.plan.balance, currency) }}
                        </td>
                        <td
                            v-if="variant === 'yearly' && summary.forecast"
                            class="py-2 pr-4 tabular-nums"
                            :class="signedMoneyClass(summary.forecast.balance)"
                        >
                            {{ formatMoney(summary.forecast.balance, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums" :class="signedMoneyClass(summary.execution.balance)">
                            {{ formatMoney(summary.execution.balance, currency) }}
                        </td>
                        <td class="py-2" />
```

Note: balance row forecast cell uses signed colors only (no muted) — zero stays neutral default foreground per spec; income/expense forecast cells remain muted.

- [ ] **Step 6: Run ESLint**

```bash
npm run lint -- resources/js/components/budget/BudgetSummaryCard.vue
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/budget/BudgetSummaryCard.vue
git commit -m "$(cat <<'EOF'
feat(budget): reorder yearly summary columns and color balance by sign

Plan → forecast → execution; muted forecast; emerald/rose balance cells.
EOF
)"
```

---

### Task 4: Update BudgetCategorySection

**Files:**
- Modify: `resources/js/components/budget/BudgetCategorySection.vue`

- [ ] **Step 1: Reorder header — forecast before actual/execution**

Replace `<thead>` row:

```vue
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4">{{ t('categories.index.fields.name') }}</th>
                        <th class="py-2 pr-4">{{ variant === 'monthly' ? t('budget.monthly.plan') : t('budget.yearly.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4 text-muted-foreground">{{ t('budget.columns.forecast') }}</th>
                        <th class="py-2 pr-4">{{ variant === 'monthly' ? t('budget.monthly.actual') : t('budget.yearly.actual') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
```

- [ ] **Step 2: Reorder body cells — plan, forecast, actual**

Replace the three amount `<td>` elements inside the `v-for` row (keep plan `EditableEstimateCell` and progress cell unchanged):

```vue
                        <td class="py-2 pr-4">
                            <EditableEstimateCell
                                :plan="planForRow(row)"
                                :currency="currency"
                                :input-id="`${variant}-plan-${row.category_id}`"
                                :placeholder="planPlaceholder"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                @start-edit="emit('start-edit', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(raw) => emit('save', row, raw)"
                            />
                        </td>
                        <td v-if="variant === 'yearly'" class="py-2 pr-4 tabular-nums text-muted-foreground">
                            {{ formatMoney(row.forecast ?? null, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(row.actual, currency) }}</td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="row.progress_percent" :category-type="categoryType" />
                        </td>
```

- [ ] **Step 3: Run ESLint**

```bash
npm run lint -- resources/js/components/budget/BudgetCategorySection.vue
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/budget/BudgetCategorySection.vue
git commit -m "$(cat <<'EOF'
feat(budget): reorder yearly category columns and mute forecast

Plan → forecast → execution in income/expense category tables.
EOF
)"
```

---

### Task 5: Verification

**Files:**
- Test: `tests/Feature/Budgets/YearlyBudgetTest.php` (existing, no changes expected)

- [ ] **Step 1: Run existing yearly budget feature tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/YearlyBudgetTest.php
```

Expected: all tests PASS (backend payload unchanged).

- [ ] **Step 2: Manual browser checklist**

Open `/budget/yearly` (current year with data):

1. Summary card columns: **Plan | Prognoza | Wykonanie | Postęp**
2. Category tables (income + expense): same column order
3. Prognoza header and values use muted gray text
4. Balance row: positive values green, negative red, zero neutral — in plan, prognoza, and wykonanie columns
5. Monthly budget (`/budget/monthly`) unchanged — no prognoza column

- [ ] **Step 3: Final commit (if any fixups needed)**

Only if verification revealed issues; otherwise skip.

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Column order Plan → Prognoza → Wykonanie → Postęp | Tasks 2, 3, 4 |
| Monthly unchanged | Tasks 2–4 scope yearly only |
| Forecast muted (`text-muted-foreground`) | Tasks 3, 4 |
| Balance signed colors all 3 columns | Task 3 Step 5 |
| Zero = neutral | Task 1 (`parsed === 0` → `''`) |
| `signedMoneyClass` in formatMoney.ts | Task 1 |
| No new Vue component | File map |
| No backend changes | YearlyBudgetTest regression only |

No placeholders. Type/name consistency: `signedMoneyClass` used in Task 3, defined in Task 1.

# Budget Pages OpenShip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle `/budget` monthly and yearly to the OpenShip language used on `/konta` and `/kategorie`: period control in the page header, a distinct summary card, one P&L categories card (income + expense), pockets as a third card — without changing PHP, props, or plan-edit behavior.

**Architecture:** New `BudgetPeriodNav` in `#headerActions`. Cards stay `<section>` components with their own tables. `BudgetCategorySection` becomes one card that owns both income and expense groups. `BudgetSummaryCard` stays a sibling card with its own `<table>` — never merged into the category table.

**Tech Stack:** Inertia Vue 3, Tailwind v3, shadcn-vue Button/Input, vue-i18n, Lucide, Pest 4 (smoke only), Prettier, `vue-tsc`.

**Spec:** `.docs/superpowers/specs/2026-08-27-budget-openship-design.md`  
**Suggested branch:** `improvement/budget-openship`

## Global Constraints

- Variant A architecture: domain UI in `components/budget/`, not `components/ui/`.
- No PHP, routes, Inertia props, estimate APIs, or `pageChrome` tab changes.
- **Hard constraint:** summary stays its own card and its own `<table>`. Do not put Income / Expenses / Balance rows in the category table. Do not extract `tbody`-root components (`BudgetSummaryRows`, `BudgetPnlHead`). Do not revive the discarded one-P&L-table layout.
- Card chrome: `rounded-2xl border border-transparent bg-card p-5 sm:p-6` — no `shadow-sm`, no `border-sidebar-border`.
- Section labels: `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`.
- `thead`: same OpenShip label classes as section labels.
- Row hover: `hover:bg-foreground/[0.03]` or `.list-row-hover`. Dividers: `border-border/50`.
- Keep `.budget-page` CSS variables and `BudgetTableColgroup` so Plan stays under Plan across the two P&L tables.
- Code, comments, commits in English. New user-facing copy: only `budget.period.previous` / `budget.period.next`.
- UI: skill `openship-inspired-ui`.
- After Vue: `npx prettier --write` on touched files. No PHP expected — skip Pint.
- Typecheck: `npm run typecheck`. If unrelated files already fail, assert **no** new errors under `resources/js/pages/budget/` or `resources/js/components/budget/`.
- Smoke: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php tests/Feature/Budgets/YearlyBudgetTest.php`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `resources/js/components/budget/BudgetPeriodNav.vue` | Previous/next period in the page header |
| Modify | `resources/js/locales/en.json`, `pl.json` | `budget.period.previous` / `budget.period.next` |
| Modify | `resources/js/pages/budget/Monthly.vue` | `#headerActions`; one `BudgetCategorySection`; no body period row |
| Modify | `resources/js/pages/budget/Yearly.vue` | Same without pockets |
| Modify | `resources/js/components/budget/BudgetProgressCell.vue` | OpenShip status chips |
| Modify | `resources/js/components/budget/BudgetSummaryRowLabel.vue` | Tile radius + hues |
| Modify | `resources/js/components/budget/BudgetSummaryCard.vue` | Own OpenShip card + table (not merged) |
| Modify | `resources/js/components/budget/BudgetCategorySection.vue` | One card, both groups, one thead, section-label rows |
| Modify | `resources/js/components/budget/BudgetPocketSection.vue` | Own OpenShip card, label, ghost manage link |
| Modify | `resources/js/components/budget/EditableEstimateCell.vue` | Lucide `stroke-width="1.7"` |
| Modify | `resources/js/components/budget/YearlyPlanEditCell.vue` | Lucide `stroke-width="1.7"` |
| Modify | `.docs/checklist.md` | Record OpenShip budget restyle |

---

### Task 1: Period nav in the header

**Files:**
- Create: `resources/js/components/budget/BudgetPeriodNav.vue`
- Modify: `resources/js/locales/en.json`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/pages/budget/Monthly.vue`
- Modify: `resources/js/pages/budget/Yearly.vue`

**Interfaces:**
- Consumes: existing `year` / `month` page props; `route('budget.monthly')` / `route('budget.yearly')`; `AppLayout` `#headerActions` slot.
- Produces:

```ts
defineProps<{
    year: number;
    month?: number;
    variant: 'monthly' | 'yearly';
}>();
```

- [ ] **Step 1: Add i18n keys**

In `resources/js/locales/en.json`, inside `"budget"`, after the `"view"` object, add:

```json
        "period": {
            "previous": "Previous period",
            "next": "Next period"
        },
```

In `resources/js/locales/pl.json`, same location:

```json
        "period": {
            "previous": "Poprzedni okres",
            "next": "Następny okres"
        },
```

Keep valid JSON commas.

- [ ] **Step 2: Create `BudgetPeriodNav.vue`**

Create `resources/js/components/budget/BudgetPeriodNav.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    year: number;
    month?: number;
    variant: 'monthly' | 'yearly';
}>();

const { t } = useI18n();

function changePeriod(delta: number): void {
    if (props.variant === 'yearly') {
        router.get(route('budget.yearly', { year: props.year + delta }));
        return;
    }

    let m = (props.month ?? 1) + delta;
    let y = props.year;
    if (m < 1) {
        m = 12;
        y -= 1;
    }
    if (m > 12) {
        m = 1;
        y += 1;
    }
    router.get(route('budget.monthly', { year: y, month: m }));
}
</script>

<template>
    <div class="flex items-center gap-1">
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="rounded-xl"
            :aria-label="t('budget.period.previous')"
            @click="changePeriod(-1)"
        >
            <ChevronLeft class="size-4" :stroke-width="1.7" aria-hidden="true" />
        </Button>
        <span class="min-w-[5.5rem] text-center text-sm font-medium tabular-nums text-foreground">
            {{ variant === 'monthly' ? `${month}/${year}` : year }}
        </span>
        <Button type="button" variant="ghost" size="icon" class="rounded-xl" :aria-label="t('budget.period.next')" @click="changePeriod(1)">
            <ChevronRight class="size-4" :stroke-width="1.7" aria-hidden="true" />
        </Button>
    </div>
</template>
```

- [ ] **Step 3: Wire monthly page**

In `resources/js/pages/budget/Monthly.vue`:

1. Add import:

```ts
import BudgetPeriodNav from '@/components/budget/BudgetPeriodNav.vue';
```

2. Remove the unused `Button` import if nothing else uses it.

3. Replace the `<AppLayout>` opening and the body period row so the template starts:

```vue
    <AppLayout>
        <template #headerActions>
            <BudgetPeriodNav :year="year" :month="month" variant="monthly" />
        </template>

        <div class="budget-page flex flex-col gap-6">
            <BudgetSummaryCard :summary="summary" :currency="currency" variant="monthly" />
```

Delete this block (do not leave it in the body):

```vue
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="changePeriod(-1)">←</Button>
                    <span class="text-sm font-medium">{{ month }}/{{ year }}</span>
                    <Button variant="outline" @click="changePeriod(1)">→</Button>
                </div>
            </div>
```

4. Keep `changePeriod` in the script — `BudgetPeriodNav` owns navigation now, so **delete** `changePeriod` from `Monthly.vue` as well.

- [ ] **Step 4: Wire yearly page**

In `resources/js/pages/budget/Yearly.vue`:

1. Add import `BudgetPeriodNav`. Remove unused `Button` import.

2. Replace the period row with:

```vue
    <AppLayout>
        <template #headerActions>
            <BudgetPeriodNav :year="year" variant="yearly" />
        </template>

        <div class="budget-page flex flex-col gap-6">
            <BudgetSummaryCard :summary="summary" :currency="currency" variant="yearly" />
```

3. Delete the body `←` / `year` / `→` row. Do not move `router.get` into the page — `BudgetPeriodNav` handles it.

- [ ] **Step 5: Format and typecheck**

Run:

```bash
npx prettier --write \
  resources/js/components/budget/BudgetPeriodNav.vue \
  resources/js/pages/budget/Monthly.vue \
  resources/js/pages/budget/Yearly.vue \
  resources/js/locales/en.json \
  resources/js/locales/pl.json
npm run typecheck
```

Expected: Prettier exits 0. Typecheck has no errors in `pages/budget/` or `components/budget/`. Unrelated pre-existing errors (if any) are not this task.

- [ ] **Step 6: Commit**

```bash
git add \
  resources/js/components/budget/BudgetPeriodNav.vue \
  resources/js/pages/budget/Monthly.vue \
  resources/js/pages/budget/Yearly.vue \
  resources/js/locales/en.json \
  resources/js/locales/pl.json
git commit -m "feat(budget): move period navigation into the page header"
```

---

### Task 2: Progress chips and summary row tiles

**Files:**
- Modify: `resources/js/components/budget/BudgetProgressCell.vue`
- Modify: `resources/js/components/budget/BudgetSummaryRowLabel.vue`

**Interfaces:**
- Consumes: existing props (`percent`, `categoryType`; `label`, `variant`). Tone logic unchanged.
- Produces: OpenShip status chips; summary tiles `rounded-lg` with emerald / red / zinc (no indigo, no rose solid fills).

- [ ] **Step 1: Restyle `BudgetProgressCell`**

Keep the `tone` computed as it is today. Replace `badgeClasses` and the template span classes with:

```ts
const badgeClasses: Record<string, string> = {
    good: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    warn: 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    bad: 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
    muted: 'border-border/50 bg-muted text-muted-foreground',
};
```

```vue
<template>
    <span v-if="percent === null" class="text-muted-foreground">—</span>
    <span
        v-else
        class="inline-flex w-fit rounded-full border px-2.5 py-0.5 text-xs font-medium tabular-nums"
        :class="badgeClasses[tone]"
    >
        {{ percent }}%
    </span>
</template>
```

Do not change the income/expense threshold rules.

- [ ] **Step 2: Restyle `BudgetSummaryRowLabel`**

Replace `config` and the tile span so tiles are `rounded-lg` and use status hues. Pass `stroke-width` 1.7 into `Icon` if the component accepts `strokeWidth` (it does).

```ts
const config = computed(() => {
    if (props.variant === 'income') {
        return {
            icon: 'arrow-up-right',
            color: '#10b981',
            tileClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        };
    }

    if (props.variant === 'expense') {
        return {
            icon: 'arrow-down-left',
            color: '#dc2626',
            tileClass: 'bg-red-500/10 text-red-600 dark:text-red-400',
        };
    }

    return {
        icon: 'scale',
        color: '#71717a',
        tileClass: 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400',
    };
});
```

```vue
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="config.tileClass" aria-hidden="true">
            <Icon :name="config.icon" class="h-4 w-4" :color="config.color" :stroke-width="1.7" />
        </span>
```

Remove indigo (`bg-indigo-50`) and rose (`bg-rose-50`) fills.

- [ ] **Step 3: Format and typecheck**

Run:

```bash
npx prettier --write \
  resources/js/components/budget/BudgetProgressCell.vue \
  resources/js/components/budget/BudgetSummaryRowLabel.vue
npm run typecheck
```

Expected: Prettier 0; no new errors under `components/budget/`.

- [ ] **Step 4: Commit**

```bash
git add \
  resources/js/components/budget/BudgetProgressCell.vue \
  resources/js/components/budget/BudgetSummaryRowLabel.vue
git commit -m "style(budget): apply OpenShip status tokens to progress and summary tiles"
```

---

### Task 3: Summary card chrome (own table)

**Files:**
- Modify: `resources/js/components/budget/BudgetSummaryCard.vue`

**Interfaces:**
- Consumes: existing props `summary`, `currency`, `variant`.
- Produces: OpenShip lifted card wrapping the **existing** summary `<table>` (still `layout="summary"`).

- [ ] **Step 1: Restyle the card and thead only**

Replace the root `<section>` class and thead/row borders. Do **not** change the three data rows, getters, or merge this table with categories.

Root:

```vue
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
```

Thead row:

```vue
                    <tr class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        <th class="py-2 pr-4" />
                        <th class="py-2 pr-4">{{ t('budget.summary.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4">{{ t('budget.summary.forecast') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.summary.execution') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
```

Each summary `<tr>`: `class="border-b border-border/50"` (drop `border-sidebar-border/40`). Keep the empty first `<th>` (row labels are Income / Expenses / Balance). Keep `BudgetTableColgroup layout="summary"`.

Remove `shadow-sm`, `bg-muted/30`, and `border-sidebar-border`.

- [ ] **Step 2: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/components/budget/BudgetSummaryCard.vue
npm run typecheck
```

Expected: Prettier 0; no new errors under `components/budget/`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/budget/BudgetSummaryCard.vue
git commit -m "style(budget): restyle summary card to OpenShip without mixing categories"
```

---

### Task 4: One P&L card for income and expense

**Files:**
- Modify: `resources/js/components/budget/BudgetCategorySection.vue`
- Modify: `resources/js/pages/budget/Monthly.vue`
- Modify: `resources/js/pages/budget/Yearly.vue`

**Interfaces:**
- Consumes: page-computed `incomeRows` / `expenseRows`; same edit emits as today.
- Produces: **stop** `title`, `categoryType`, `rows`. New props:

```ts
defineProps<{
    incomeRows: BudgetRow[];
    expenseRows: BudgetRow[];
    currency: CurrencyDisplay;
    variant: 'monthly' | 'yearly';
    editingCategoryId: number | null;
    editingMode?: 'plan' | 'align' | null;
    planPlaceholder: string;
    monthlyPlanPlaceholder?: string;
    monthlyPlanLabel?: string;
}>();
```

Emits unchanged: `start-edit`, `start-align`, `cancel`, `save`, `save-yearly`.

- [ ] **Step 1: Rewrite `BudgetCategorySection.vue`**

Replace the file with one card, one table, two `<tbody>` groups (income then expense). Use `group.key` for `BudgetProgressCell` `category-type`. Empty group = label + zero data rows.

```vue
<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import BudgetTableColgroup from '@/components/budget/BudgetTableColgroup.vue';
import EditableEstimateCell from '@/components/budget/EditableEstimateCell.vue';
import YearlyPlanEditCell from '@/components/budget/YearlyPlanEditCell.vue';
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type BudgetRow = {
    category_id: number;
    name: string;
    icon: string;
    color: string;
    type: string;
    monthly_plan?: string | null;
    annual_plan?: string | null;
    monthly_template?: string | null;
    actual: string;
    forecast?: string;
    progress_percent: number | null;
};

const props = defineProps<{
    incomeRows: BudgetRow[];
    expenseRows: BudgetRow[];
    currency: CurrencyDisplay;
    variant: 'monthly' | 'yearly';
    editingCategoryId: number | null;
    editingMode?: 'plan' | 'align' | null;
    planPlaceholder: string;
    monthlyPlanPlaceholder?: string;
    monthlyPlanLabel?: string;
}>();

const emit = defineEmits<{
    'start-edit': [categoryId: number];
    'start-align': [categoryId: number];
    cancel: [];
    save: [row: BudgetRow, rawValue: string];
    'save-yearly': [row: BudgetRow, annualRaw: string, monthlyRaw: string];
}>();

const { t } = useI18n();

const columnCount = computed(() => (props.variant === 'yearly' ? 5 : 4));

const groups = computed(() => [
    { key: 'income' as const, rows: props.incomeRows },
    { key: 'expense' as const, rows: props.expenseRows },
]);

function planForRow(row: BudgetRow): string | null {
    return props.variant === 'monthly' ? (row.monthly_plan ?? null) : (row.annual_plan ?? null);
}
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="category" :period="variant" />
                <thead>
                    <tr class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        <th class="py-2 pr-4">{{ t('categories.index.fields.name') }}</th>
                        <th class="py-2 pr-4">{{ variant === 'monthly' ? t('budget.monthly.plan') : t('budget.yearly.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4">{{ t('budget.columns.forecast') }}</th>
                        <th class="py-2 pr-4">{{ variant === 'monthly' ? t('budget.monthly.actual') : t('budget.yearly.actual') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody v-for="group in groups" :key="group.key">
                    <tr>
                        <th
                            :colspan="columnCount"
                            scope="colgroup"
                            class="py-3 pr-4 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60"
                        >
                            {{ t(`budget.sections.${group.key}`) }}
                        </th>
                    </tr>
                    <tr
                        v-for="row in group.rows"
                        :key="row.category_id"
                        class="list-row-hover border-b border-border/50 hover:bg-foreground/[0.03]"
                    >
                        <td class="py-2 pr-4">
                            <CategoryBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
                        </td>
                        <td class="py-2 pr-4">
                            <YearlyPlanEditCell
                                v-if="variant === 'yearly'"
                                :annual-plan="row.annual_plan ?? null"
                                :monthly-template="row.monthly_template ?? null"
                                :currency="currency"
                                :input-id-prefix="`${variant}-plan-${row.category_id}`"
                                :annual-placeholder="planPlaceholder"
                                :monthly-placeholder="monthlyPlanPlaceholder ?? ''"
                                :monthly-plan-label="monthlyPlanLabel ?? t('budget.yearly.monthlyPlanLabel')"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                @start-edit="emit('start-edit', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(annualRaw, monthlyRaw) => emit('save-yearly', row, annualRaw, monthlyRaw)"
                            />
                            <EditableEstimateCell
                                v-else
                                :plan="planForRow(row)"
                                :currency="currency"
                                :input-id="`${variant}-plan-${row.category_id}`"
                                :placeholder="planPlaceholder"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :align-label="t('budget.estimate.align', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                :mode="editingCategoryId === row.category_id ? (editingMode ?? 'plan') : null"
                                :align-value="row.actual"
                                :show-align-button="variant === 'monthly'"
                                @start-edit="emit('start-edit', row.category_id)"
                                @start-align="emit('start-align', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(raw) => emit('save', row, raw)"
                            />
                        </td>
                        <td v-if="variant === 'yearly'" class="py-2 pr-4 tabular-nums text-muted-foreground">
                            {{ formatMoney(row.forecast ?? null, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(row.actual, currency) }}</td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="row.progress_percent" :category-type="group.key" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
```

Do **not** import or render `BudgetSummaryCard` inside this file.

- [ ] **Step 2: Mount once on monthly**

In `resources/js/pages/budget/Monthly.vue`, replace the two `<BudgetCategorySection>` blocks with:

```vue
            <BudgetCategorySection
                :income-rows="incomeRows"
                :expense-rows="expenseRows"
                :currency="currency"
                variant="monthly"
                :editing-category-id="editingCategoryId"
                :editing-mode="editingMode"
                :plan-placeholder="t('budget.monthly.planPlaceholder')"
                @start-edit="startEdit"
                @start-align="startAlign"
                @cancel="cancelEdit"
                @save="saveMonthlyEstimate"
            />
```

Keep `BudgetSummaryCard` **above** this block and `BudgetPocketSection` **below** it. Keep `.budget-page` scoped CSS variables.

- [ ] **Step 3: Mount once on yearly**

In `resources/js/pages/budget/Yearly.vue`, replace the two `<BudgetCategorySection>` blocks with:

```vue
            <BudgetCategorySection
                :income-rows="incomeRows"
                :expense-rows="expenseRows"
                :currency="currency"
                variant="yearly"
                :editing-category-id="editingCategoryId"
                :plan-placeholder="t('budget.yearly.planPlaceholder')"
                :monthly-plan-placeholder="t('budget.yearly.monthlyPlanPlaceholder')"
                :monthly-plan-label="t('budget.yearly.monthlyPlanLabel')"
                @start-edit="startEdit"
                @cancel="cancelEdit"
                @save-yearly="saveYearlyPlan"
            />
```

Keep `BudgetSummaryCard` above it. Yearly has no pockets block.

- [ ] **Step 4: Format and typecheck**

Run:

```bash
npx prettier --write \
  resources/js/components/budget/BudgetCategorySection.vue \
  resources/js/pages/budget/Monthly.vue \
  resources/js/pages/budget/Yearly.vue
npm run typecheck
```

Expected: Prettier 0; no errors in the three files. Confirm monthly still has **two** card components before pockets (`BudgetSummaryCard` then `BudgetCategorySection`), not one wrapping table.

- [ ] **Step 5: Commit**

```bash
git add \
  resources/js/components/budget/BudgetCategorySection.vue \
  resources/js/pages/budget/Monthly.vue \
  resources/js/pages/budget/Yearly.vue
git commit -m "refactor(budget): put income and expense in one P&L card"
```

---

### Task 5: Pockets card and plan-edit icon stroke

**Files:**
- Modify: `resources/js/components/budget/BudgetPocketSection.vue`
- Modify: `resources/js/components/budget/EditableEstimateCell.vue`
- Modify: `resources/js/components/budget/YearlyPlanEditCell.vue`

**Interfaces:**
- Consumes: existing `rows` prop; existing edit-cell props.
- Produces: OpenShip pockets card (still hidden when `rows.length === 0`); Lucide icons at `stroke-width="1.7"`.

- [ ] **Step 1: Restyle `BudgetPocketSection`**

Replace the root `<section>` and header. Keep the pocket table, `BudgetTableColgroup layout="pocket"`, movement cell, and `v-if="rows.length > 0"`.

```vue
    <section v-if="rows.length > 0" class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                {{ t('budget.monthly.pockets_section') }}
            </h2>
            <Button variant="ghost" class="h-auto px-2 py-1 text-xs text-muted-foreground" as-child>
                <Link :href="route('pockets.index')">{{ t('budget.monthly.manage_pockets') }}</Link>
            </Button>
        </div>
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="pocket" period="monthly" />
                <thead>
                    <tr class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
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
                        class="list-row-hover border-b border-border/50 hover:bg-foreground/[0.03]"
                    >
```

Keep the rest of each row (`PocketBadge`, `formatMoney`, `BudgetPocketMovementCell`, `BudgetProgressCell`) unchanged. Do not use `variant="link"`. Do not put this table inside `BudgetCategorySection`.

- [ ] **Step 2: Icon stroke on edit cells**

In `resources/js/components/budget/EditableEstimateCell.vue`, add `:stroke-width="1.7"` to every Lucide icon:

```vue
            <Pencil class="h-4 w-4 text-muted-foreground" :stroke-width="1.7" />
```

```vue
            <ArrowRightLeft class="h-4 w-4 text-muted-foreground" :stroke-width="1.7" />
```

```vue
            <Check class="h-4 w-4 text-emerald-600 dark:text-emerald-400" :stroke-width="1.7" />
```

```vue
            <X class="h-4 w-4 text-muted-foreground" :stroke-width="1.7" />
```

Add `rounded-xl` to the editing `Input` class list (`class="h-8 w-28 rounded-xl tabular-nums"`). Add `hover:bg-foreground/[0.06]` on the ghost icon buttons if they do not already get it from `variant="ghost"` (the primitive already has it — do not fight it; leave `variant="ghost" size="icon"`).

In `resources/js/components/budget/YearlyPlanEditCell.vue`, the same `stroke-width="1.7"` on `Pencil`, `Check`, and `X`, and `rounded-xl` on both editing inputs.

Do not change PATCH validation, Enter/Esc, or align-to-actual.

- [ ] **Step 3: Format and typecheck**

Run:

```bash
npx prettier --write \
  resources/js/components/budget/BudgetPocketSection.vue \
  resources/js/components/budget/EditableEstimateCell.vue \
  resources/js/components/budget/YearlyPlanEditCell.vue
npm run typecheck
```

Expected: Prettier 0; no new errors under `components/budget/`.

- [ ] **Step 4: Commit**

```bash
git add \
  resources/js/components/budget/BudgetPocketSection.vue \
  resources/js/components/budget/EditableEstimateCell.vue \
  resources/js/components/budget/YearlyPlanEditCell.vue
git commit -m "style(budget): restyle pockets card and plan-edit icons"
```

---

### Task 6: Smoke tests and checklist

**Files:**
- Modify: `.docs/checklist.md`
- Test: `tests/Feature/Budgets/MonthlyBudgetTest.php`
- Test: `tests/Feature/Budgets/YearlyBudgetTest.php`

**Interfaces:**
- Consumes: completed Vue restyle from Tasks 1–5.
- Produces: checklist tick; Pest green.

- [ ] **Step 1: Run budget feature tests**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php tests/Feature/Budgets/YearlyBudgetTest.php
```

Expected: PASS (all tests in those files). If Sail is down, start it and rerun — do not skip.

- [ ] **Step 2: Tick checklist**

In `.docs/checklist.md`, immediately after the line:

```
- [x] Widoki budżetu: `budget/Monthly.vue`, `budget/Yearly.vue`
```

insert:

```
- [x] Chrome OpenShip: okres w nagłówku, karta podsumowania osobno od P&L, kieszenie osobną kartą **[plan 2026-08-27-budget-openship]**
```

- [ ] **Step 3: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: record OpenShip budget pages restyle"
```

---

### Task 7: Manual verification

**Files:** none (browser only).

**Interfaces:**
- Consumes: running app (`composer run dev` or Sail + `npm run dev`).
- Produces: visual confirmation against the spec success criteria.

- [ ] **Step 1: Monthly**

Open `/budget` (monthly). Confirm:

1. Period control is in the **header**, not above the cards. Previous/next changes month.
2. **Three** lifted cards when pockets exist: summary, P&L (income + expense), pockets. Grey gap between them (`gap-6`).
3. Summary rows (Income / Expenses / Balance) are **not** inside the category table.
4. P&L card has one thead, then PRZYCHODY / INCOME label, then WYDATKI / EXPENSES label.
5. Inline plan edit still saves (pencil → input → check).
6. With no pockets, the third card is absent.
7. Dark mode: cards lift off the floor; chips use 10/20 status tokens, not solid `emerald-100`.
8. Narrow viewport: tables scroll horizontally inside the card.

- [ ] **Step 2: Yearly**

Open the Yearly tab. Confirm: header year nav; **two** cards (summary + P&L); Forecast column present; no pockets card; yearly plan edit (annual + monthly inputs) still saves.

- [ ] **Step 3: Stop**

If anything fails, fix on this branch before merging. Do not “verify” with a single screenshot.

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Period in `#headerActions` | 1 |
| Summary own card + own table | 3 (guarded in 4) |
| One P&L card, one thead, section labels | 4 |
| Pockets own card, hidden if empty | 5 |
| Progress chips + summary tile hues | 2 |
| Plan-edit stroke 1.7 | 5 |
| i18n `budget.period.*` | 1 |
| Checklist | 6 |
| Manual QA | 7 |
| No PHP / no tbody-root merge | Global constraints + Task 4 note |

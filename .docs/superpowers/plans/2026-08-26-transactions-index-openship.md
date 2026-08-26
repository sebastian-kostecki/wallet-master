# Transactions Index OpenShip List Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the `/transakcje` list block (table, mobile rows, empty states, pagination) to the OpenShip language already used on `/konta`, without changing filters, summary cards, banners, or modals.

**Architecture:** Extract `TransactionRow` (desktop `<tr>` + mobile stack via `variant`) so row tokens live in one file. Lifted `rounded-2xl bg-card` wraps only rows/skeleton; empty and `PaginationBar` sit on the grey page. `PaginationBar` is restyled in place (transactions-only). No PHP.

**Tech Stack:** Inertia Vue 3, Tailwind v3, shadcn-vue Button/Tooltip, vue-i18n, Lucide, Pest 4 (smoke only), Prettier, `vue-tsc`.

**Spec:** `.docs/superpowers/specs/2026-08-26-transactions-index-openship-design.md`  
**Suggested branch:** `improvement/transactions-index-openship`

## Global Constraints

- Variant A: no `app/Services/`, no `Data/*Result`, no `components/ui/` table primitive, no `DataTable`.
- No PHP, routes, Inertia props, or query persistence changes.
- Summary income/expense cards, header filters, Add menu, banners, and dialogs are **untouched** (summary keeps `text-rose-600`).
- Columns, sort, tooltips, read-only rows, and pagination query/telemetry stay.
- Row color for money/type: OpenShip status recipe; expense `rose` → `red` **only** inside `TransactionRow`.
- Code, comments, commits in English. User-facing copy in `pl.json` / `en.json`.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`.
- After Vue: `npx prettier --write` on touched files; `npm run typecheck`. After PHP (none expected): skip Pint. Smoke: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionIndexTest.php`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `resources/js/components/transactions/TransactionRow.vue` | Desktop `<tr>` + mobile row; type wells, badge, amount colors, actions |
| Modify | `resources/js/pages/transactions/Index.vue` | Card vs floor; thead tokens; two `v-for`s of `TransactionRow`; OpenShip empty; drop row helpers |
| Modify | `resources/js/components/pagination/PaginationBar.vue` | Page-number radius and hover/active |
| Modify | `resources/js/locales/pl.json`, `en.json` | Nested `empty.filtered|firstUse` heading/message |
| Unchanged | `resources/js/components/page/TablePage.vue` | Keep API; pager already after default slot |
| Modify | `.docs/checklist.md` | Record list restyle under §4 |

Index **keeps** `toNumber`, `formatAmount`, `formatCurrencySymbol` (summary cards). Passes `formatAmount` and `formatCurrencySymbol` into the row. Date, truncate, raw-statement, type-icon, and account-type-icon helpers **move** into `TransactionRow`.

---

### Task 1: Extract `TransactionRow` with OpenShip row tokens

**Files:**
- Create: `resources/js/components/transactions/TransactionRow.vue`
- Modify: `resources/js/pages/transactions/Index.vue`

**Interfaces:**
- Consumes: list `Transaction` object (same fields as today’s Index row); `formatAmount`, `formatCurrencySymbol` from Index.
- Produces:

```ts
type TransactionRowVariant = 'desktop' | 'mobile';

defineProps<{
    transaction: Transaction;
    variant: TransactionRowVariant;
    deleteProcessing: boolean;
    formatAmount: (value: string | number) => string;
    formatCurrencySymbol: (account: Transaction['account']) => string;
}>();

defineEmits<{
    edit: [transaction: Transaction];
    delete: [transactionId: number];
}>();
```

- [ ] **Step 1: Create `TransactionRow.vue`**

Create `resources/js/components/transactions/TransactionRow.vue`. Root must be a single `v-if` / `v-else` pair (`<tr>` vs `<div>`) so the desktop instance is a valid `<tbody>` child.

```vue
<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ArrowDownLeft, ArrowRightLeft, ArrowUpRight, Pencil, PiggyBank, Scale, Trash2, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    bank_icon_url: string | null;
    bank?: string;
    currency?: {
        symbol: string | null;
    } | null;
};

type TransactionCategory = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    icon: string;
    color: string;
};

export type Transaction = {
    id: number;
    account_id: number;
    date: string;
    booked_at: string;
    date_relative: string;
    amount: string | number;
    type: 'income' | 'expense' | string;
    description: string;
    subject: string | null;
    raw_statement_description: string | null;
    transfer_id: string | null;
    category_id: number | null;
    category: TransactionCategory | null;
    pocket_id: number | null;
    pocket: { id: number; name: string } | null;
    account: Account | null;
    currency: { id: number; code: string; symbol: string | null; precision: number } | null;
};

const props = defineProps<{
    transaction: Transaction;
    variant: 'desktop' | 'mobile';
    deleteProcessing: boolean;
    formatAmount: (value: string | number) => string;
    formatCurrencySymbol: (account: Transaction['account']) => string;
}>();

defineEmits<{
    edit: [transaction: Transaction];
    delete: [transactionId: number];
}>();

const { t, locale } = useI18n();

const tx = computed(() => props.transaction);

function toNumber(value: string | number): number {
    if (typeof value === 'number') {
        return value;
    }

    const parsed = Number(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

function normalizeDateIso(input: string): string {
    const trimmed = input.trim();
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);

    return match?.[1] ?? trimmed;
}

function formatDateIsoToDots(input: string): string {
    const iso = normalizeDateIso(input);
    const parts = iso.split('-');
    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;
    if (!yyyy || !mm || !dd) {
        return input;
    }

    return `${dd}.${mm}.${yyyy}`;
}

function transactionDisplayDateIso(row: Transaction): string {
    const booked = normalizeDateIso(row.booked_at ?? '');
    if (booked !== '') {
        return booked;
    }

    return normalizeDateIso(row.date);
}

function operationDateRelative(dateIso: string): string {
    const iso = normalizeDateIso(dateIso);
    const parts = iso.split('-');
    if (parts.length !== 3) {
        return '';
    }

    const y = Number(parts[0]);
    const m = Number(parts[1]);
    const d = Number(parts[2]);
    if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
        return '';
    }

    const target = new Date(y, m - 1, d);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    const diffDays = Math.round((target.getTime() - today.getTime()) / 86400000);

    const lng = locale.value === 'pl' ? 'pl' : 'en';
    const rtf = new Intl.RelativeTimeFormat(lng, { numeric: 'auto' });

    return rtf.format(diffDays, 'day');
}

function hasRawStatementDescription(row: Transaction): boolean {
    return (row.raw_statement_description ?? '').trim() !== '';
}

function truncateText(input: string | null | undefined, maxLength: number): { text: string; isTruncated: boolean } {
    const value = (input ?? '').trim();
    if (value === '') {
        return { text: '—', isTruncated: false };
    }

    if (value.length <= maxLength) {
        return { text: value, isTruncated: false };
    }

    const slice = value.slice(0, Math.max(0, maxLength));
    const lastSpace = slice.lastIndexOf(' ');
    const trimmed = (lastSpace >= Math.floor(maxLength * 0.6) ? slice.slice(0, lastSpace) : slice).trimEnd();

    return { text: `${trimmed}…`, isTruncated: true };
}

type TransactionIconVariant = 'internal' | 'expense' | 'income' | 'adjustment';

function transactionVariant(row: Transaction): TransactionIconVariant {
    if (row.type === 'adjustment') {
        return 'adjustment';
    }

    if (row.transfer_id) {
        return 'internal';
    }

    return row.type === 'expense' ? 'expense' : 'income';
}

function transactionIcon(row: Transaction) {
    const variant = transactionVariant(row);

    if (variant === 'internal') {
        return {
            component: ArrowRightLeft,
            containerClass: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        } as const;
    }

    if (variant === 'adjustment') {
        return {
            component: Scale,
            containerClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        } as const;
    }

    if (variant === 'expense') {
        return {
            component: ArrowDownLeft,
            containerClass: 'bg-red-500/10 text-red-600 dark:text-red-400',
        } as const;
    }

    return {
        component: ArrowUpRight,
        containerClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    } as const;
}

const adjustmentBadgeClass =
    'inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400';

function amountClass(row: Transaction): string {
    return toNumber(row.amount) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
}

function resolveAccountTypeIcon(type: string) {
    return type === 'savings' ? PiggyBank : Wallet;
}

const displayDateIso = computed(() => transactionDisplayDateIso(tx.value));
</script>
```

Template: copy the **existing** desktop `<tr>` body and mobile row from `resources/js/pages/transactions/Index.vue` (desktop `tbody` `tr` through its `</tr>`; mobile `v-for` inner `div`) into this file as:

```vue
<template>
    <tr
        v-if="variant === 'desktop'"
        class="list-row-hover border-b border-border/50 last:border-b-0"
    >
        <!-- six <td>s: same content as Index, with substitutions below -->
    </tr>
    <div v-else class="list-row-hover border-b border-border/50 px-3 py-2.5 last:border-b-0">
        <!-- same mobile content as Index, substitutions below -->
    </div>
</template>
```

Apply these substitutions while copying (do not change tooltip structure, truncation limits, or action wiring):

| Old | New |
|-----|-----|
| `td` `px-6 py-4` | `px-3 py-2.5` |
| `border-amber-200 bg-amber-50 … text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400` | `:class="adjustmentBadgeClass"` on the badge `<span>` |
| `cn('font-medium', toNumber(tx.amount) >= 0 ? 'text-emerald-…' : 'text-rose-…')` | `'font-medium ' + amountClass(tx)` (or `:class="['font-medium', amountClass(tx)]"`) |
| mobile amount `text-rose-600 dark:text-rose-400` | `amountClass(tx)` |
| `transactionIcon(tx)` | keep; uses new container classes from script |
| `@click="openEditDialog(tx)"` | `@click="$emit('edit', tx)"` |
| `@click="openDeleteDialog(tx.id)"` | `@click="$emit('delete', tx.id)"` |
| `formatAmount(...)` / `formatCurrencySymbol(...)` | `formatAmount(...)` / `formatCurrencySymbol(...)` via props |
| `formatDateIsoToDots(transactionDisplayDateIso(tx))` | `formatDateIsoToDots(displayDateIso)` |
| `tx.date_relative \|\| operationDateRelative(...)` | same helpers, local |

Use `tx` from the computed (or `transaction` prop) consistently. Do not import `cn`.

- [ ] **Step 2: Wire Index desktop + mobile `v-for`s**

In `Index.vue`:

1. Import `TransactionRow`.
2. Replace the desktop `<tr v-for>` with:

```vue
<TransactionRow
    v-for="tx in transactions.data"
    :key="tx.id"
    :transaction="tx"
    variant="desktop"
    :delete-processing="deleteProcessing"
    :format-amount="formatAmount"
    :format-currency-symbol="formatCurrencySymbol"
    @edit="openEditDialog"
    @delete="openDeleteDialog"
/>
```

inside `<tbody>`.

3. Replace the mobile wrapper. Remove `divide-y divide-sidebar-border/70 dark:divide-sidebar-border` and the inner `v-for` div. Use:

```vue
<div class="md:hidden">
    <TransactionRow
        v-for="tx in transactions.data"
        :key="tx.id"
        :transaction="tx"
        variant="mobile"
        :delete-processing="deleteProcessing"
        :format-amount="formatAmount"
        :format-currency-symbol="formatCurrencySymbol"
        @edit="openEditDialog"
        @delete="openDeleteDialog"
    />
</div>
```

4. Update `<thead>`:

```vue
<thead
    class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60"
>
```

Change every `th` `px-6 py-3` → `px-3 py-2`. Keep `w-36`, `w-56`, `w-64`, `w-44`, `w-28`, `xl:w-64 2xl:w-72`, sort buttons, and `aria-sort`.

5. Remove from Index (now unused): `CategoryBadge`; `Tooltip*`; `cn`; lucide `ArrowDownLeft`, `ArrowUpRight`, `ArrowRightLeft`, `Pencil`, `PiggyBank`, `Scale`, `Trash2`, `Wallet`; functions `normalizeDateIso`, `formatDateIsoToDots`, `transactionDisplayDateIso`, `operationDateRelative`, `transactionVariant`, `transactionIcon`, `hasRawStatementDescription`, `truncateText`, `resolveAccountTypeIcon`, `accountTypeIcons`, type `TransactionIconVariant`.

Keep `toNumber`, `formatAmount`, `formatCurrencySymbol` (summary). Keep sort lucide `ArrowUp`, `ArrowDown`, `ArrowUpDown`.

- [ ] **Step 3: Format and typecheck**

```bash
npx prettier --write resources/js/components/transactions/TransactionRow.vue resources/js/pages/transactions/Index.vue
npm run typecheck
```

Expected: `vue-tsc` exit 0. If `Transaction` export clashes with Index’s local `Transaction`, drop `export` on the row type (Index does not need to import it).

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/transactions/TransactionRow.vue resources/js/pages/transactions/Index.vue
git commit -m "$(cat <<'EOF'
feat(transactions): extract OpenShip transaction rows

EOF
)"
```

---

### Task 2: Empty states on the floor + list card split

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`
- Modify: `resources/js/pages/transactions/Index.vue`

**Interfaces:**
- Consumes: `isFilteredEmpty`, `isFirstUseEmpty`, `openCreateDialog`, `clearFilters`, `hasImportRoute` (unchanged).
- Produces: nested i18n keys:

```text
transactions.index.empty.filtered.heading
transactions.index.empty.filtered.message
transactions.index.empty.firstUse.heading
transactions.index.empty.firstUse.message
transactions.index.empty.import
```

- [ ] **Step 1: Nest empty strings in both locale files**

`resources/js/locales/pl.json` — replace the current `transactions.index.empty` object with:

```json
"empty": {
    "filtered": {
        "heading": "Brak wyników",
        "message": "Brak transakcji dla wybranych filtrów."
    },
    "firstUse": {
        "heading": "Brak transakcji",
        "message": "Nie masz jeszcze żadnych transakcji."
    },
    "import": "Zaimportuj plik"
}
```

`resources/js/locales/en.json`:

```json
"empty": {
    "filtered": {
        "heading": "No results",
        "message": "No transactions match your filters."
    },
    "firstUse": {
        "heading": "No transactions",
        "message": "You don't have any transactions yet."
    },
    "import": "Import file"
}
```

Grep: no remaining `t('transactions.index.empty.filtered')` or `t('transactions.index.empty.firstUse')` as a full key (must be `.heading` / `.message`).

- [ ] **Step 2: Split card vs floor in Index**

Replace the wrapper that currently is `rounded-xl border border-sidebar-border/70 dark:border-sidebar-border` around skeleton + `TablePage` with:

```vue
<div :aria-busy="isLoading ? 'true' : 'false'">
    <div v-if="isLoading" class="overflow-hidden rounded-2xl border border-transparent bg-card p-5">
        <div class="grid gap-3">
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
        </div>
    </div>

    <TablePage v-else ...>
        <template #empty>
            <section
                v-if="isFilteredEmpty"
                role="region"
                aria-labelledby="transactions-empty-heading"
                class="flex flex-col items-center py-16 text-center"
            >
                <h2 id="transactions-empty-heading" class="text-2xl font-medium tracking-tight">
                    {{ t('transactions.index.empty.filtered.heading') }}
                </h2>
                <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                    {{ t('transactions.index.empty.filtered.message') }}
                </p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Button type="button" @click="openCreateDialog">{{ t('transactions.index.addTransaction') }}</Button>
                    <Button variant="ghost" type="button" @click="clearFilters">{{ t('transactions.index.filters.clear') }}</Button>
                </div>
            </section>

            <section
                v-else
                role="region"
                aria-labelledby="transactions-empty-heading"
                class="flex flex-col items-center py-16 text-center"
            >
                <h2 id="transactions-empty-heading" class="text-2xl font-medium tracking-tight">
                    {{ t('transactions.index.empty.firstUse.heading') }}
                </h2>
                <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                    {{ t('transactions.index.empty.firstUse.message') }}
                </p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Button type="button" @click="openCreateDialog">{{ t('transactions.index.addTransaction') }}</Button>
                    <Button v-if="hasImportRoute" variant="ghost" as-child>
                        <Link :href="route('imports.index')">{{ t('transactions.index.empty.import') }}</Link>
                    </Button>
                </div>
            </section>
        </template>

        <div class="overflow-hidden rounded-2xl border border-transparent bg-card">
            <div class="hidden md:block">
                <!-- existing table + TransactionRow desktop -->
            </div>
            <div class="md:hidden">
                <!-- existing TransactionRow mobile -->
            </div>
        </div>
    </TablePage>
</div>
```

Do **not** put `bg-card` on empty. Leave summary cards (`rounded-xl border border-sidebar-border/70` + `text-rose-600`) exactly as they are.

- [ ] **Step 3: Format and typecheck**

```bash
npx prettier --write resources/js/pages/transactions/Index.vue resources/js/locales/pl.json resources/js/locales/en.json
npm run typecheck
```

Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/transactions/Index.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "$(cat <<'EOF'
feat(transactions): lift list card and restyle empty states

EOF
)"
```

---

### Task 3: Restyle `PaginationBar`

**Files:**
- Modify: `resources/js/components/pagination/PaginationBar.vue`

**Interfaces:**
- Consumes: existing `paginator`, `query`, `pageUrl`, `trackPageChanged` (unchanged).
- Produces: same pager behavior; OpenShip page-number chips.

- [ ] **Step 1: Update page-number classes**

In `PaginationBar.vue`, replace the middle `Link` `:class` `cn(...)` with:

```vue
:class="
    cn(
        'inline-flex h-9 min-w-9 items-center justify-center rounded-xl px-2 text-sm font-medium transition-colors',
        link.active
            ? 'bg-primary text-primary-foreground'
            : 'text-muted-foreground hover:bg-foreground/[0.06] hover:text-foreground',
        link.page === null ? 'pointer-events-none opacity-50' : '',
    )
"
```

Do not change Prev/Next (`Button variant="outline" size="sm"`), per-page `DropdownSelect`, labels, `route('transactions.index')`, or telemetry.

- [ ] **Step 2: Format**

```bash
npx prettier --write resources/js/components/pagination/PaginationBar.vue
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/pagination/PaginationBar.vue
git commit -m "$(cat <<'EOF'
style(transactions): round pagination chips to OpenShip tokens

EOF
)"
```

---

### Task 4: Smoke tests, checklist, browser check

**Files:**
- Modify: `.docs/checklist.md`
- Test: `tests/Feature/Transactions/TransactionIndexTest.php` (run only)

**Interfaces:**
- Consumes: Tasks 1–3 UI.
- Produces: checklist note; passing index Pest.

- [ ] **Step 1: Run index Pest smoke**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionIndexTest.php
```

Expected: PASS (no PHP changes; Inertia still `transactions/Index`).

- [ ] **Step 2: Checklist**

Under `.docs/checklist.md` §4 “Lista transakcji”, add a checked item:

```markdown
  - [x] Lista OpenShip: karta wierszy, empty na podłodze, pager **[plan 2026-08-26-transactions-index-openship]**
```

Do not edit PRD (visual-only).

- [ ] **Step 3: Browser verification**

Use `get-absolute-url` if sharing a URL. Exercise as a logged-in user:

1. `/transakcje` with rows: one `rounded-2xl` card, compact rows, hover, no zebra, pager **below** the card on grey.
2. Dark mode: card lift, red/emerald amounts, no `rose` on rows.
3. Viewport `< md`: stacked rows, actions still work (edit/delete dialogs).
4. Filtered empty: heading „Brak wyników”, ghost „Wyczyść filtry”, no card around empty.
5. First-use empty (no rows, no filters): heading „Brak transakcji”, ghost import if route exists.
6. Summary cards still bordered `rounded-xl` with rose expense (unchanged).
7. Sort date/amount and pagination still update the list.

- [ ] **Step 4: Commit checklist**

```bash
git add .docs/checklist.md
git commit -m "$(cat <<'EOF'
docs: record OpenShip transactions list restyle

EOF
)"
```

---

## Self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| List block only (not summary/filters/banners/modals) | Tasks 1–3 leave those nodes alone |
| Compact `px-3 py-2.5` + `list-row-hover` + `border-border/50` | Task 1 |
| `TransactionRow` variant desktop `<tr>` / mobile `<div>`; two Index `v-for`s | Task 1 |
| Thead 11px uppercase tracking | Task 1 |
| Type wells + adjustment pill + amount red/emerald | Task 1 |
| Mobile drops `divide-y` | Task 1 |
| Empty heading+message, ghost secondary, floor not card | Task 2 |
| Nested i18n keys | Task 2 |
| Card around rows/skeleton only; pager on floor | Tasks 2–3 (`TablePage` already places pager after slot) |
| `PaginationBar` `rounded-xl` + ink active | Task 3 |
| No PHP / no `DataTable` / no `TablePage` API change | All |
| Pest smoke + browser | Task 4 |
| `rose` remains on summary cards | Task 2 explicit |

No TBD/TODO in tasks. Prop names (`formatAmount`, `formatCurrencySymbol`, `variant`, `deleteProcessing`, emits `edit`/`delete`) are consistent across tasks.

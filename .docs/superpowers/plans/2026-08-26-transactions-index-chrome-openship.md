# Transactions Index OpenShip Chrome Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle always-visible `/transakcje` chrome (Add menu, filter bar, income/expense summary cards) to the OpenShip language already used on the list and `/konta`, without touching banners or modal interiors.

**Architecture:** Relocate `TransactionsIndexHeaderFilters` from `#headerActions` to the page body (prepended above existing banners). Restyle summary cards in `Index.vue`. Token-only restyle of `DateRangePickerInput` (transactions-only) and the Add `DropdownMenuContent` call site. No new Vue files, no PHP.

**Tech Stack:** Inertia Vue 3, Tailwind v3, shadcn-vue Button/DropdownMenu/Popover, vue-i18n, Pest 4 (smoke only), Prettier.

**Spec:** `.docs/superpowers/specs/2026-08-26-transactions-index-chrome-openship-design.md`  
**Suggested branch:** `improvement/transactions-index-chrome-openship`

## Global Constraints

- Variant A: no `app/Services/`, no new `components/ui/` primitives, no `DataTable`.
- No PHP, routes, Inertia props, i18n keys, or query persistence changes.
- Do not edit `DropdownSelect.vue` or `components/ui/*` primitives.
- Do not restyle banners, `TransactionFormDialog`, `DeleteTransactionDialog`, `ImportDialog`, or `transfers.create`.
- Do not extract `TransactionsSummaryCards` or rename `TransactionsIndexHeaderFilters`.
- Cash icon gradient in the filter leading slot stays.
- Expense `rose` → `red` **only** on the summary amount (list rows already `red`).
- Body order: filters (new first child) → existing banners → summary → list. Do not swap banners with summary.
- Code, comments, commits in English.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`.
- After Vue: `npx prettier --write` on touched files. `npm run typecheck` — fail the task only if **new** errors appear in files this plan touches; pre-existing `vue-tsc` failures elsewhere are out of scope.
- Smoke: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionIndexTest.php tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/pages/transactions/Index.vue` | Add-only `#headerActions`; filters as first body child; summary card tokens; Add menu `rounded-2xl` |
| Modify | `resources/js/components/transactions/TransactionsIndexHeaderFilters.vue` | Floor layout: drop `flex-1` / `justify-end`; wrap + start align |
| Modify | `resources/js/components/forms/DateRangePickerInput.vue` | Clear/preset radius + hover; popover `rounded-2xl` at call site |
| Modify | `.docs/checklist.md` | Record chrome restyle under §4 |
| Unchanged | `resources/js/components/forms/DropdownSelect.vue` | Shared; leave `h-10` outline |

---

### Task 1: Move filters onto the page floor

**Files:**
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/components/transactions/TransactionsIndexHeaderFilters.vue`

**Interfaces:**
- Consumes: existing `TransactionsIndexHeaderFilters` props (`accounts`, `categories`, `filters`, `serverErrors`, `isLoading`).
- Produces: same filter component, rendered as the first child of the page `flex flex-col gap-6` column; `#headerActions` contains only the Add `DropdownMenu`.

- [ ] **Step 1: Header — Add menu only**

In `resources/js/pages/transactions/Index.vue`, replace the `#headerActions` block so the wrapper div and the filter component are gone. Leave `DropdownMenuContent` as `class="w-56"` (Task 3 adds `rounded-2xl`).

```vue
        <template #headerActions>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button class="sm:shrink-0">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        {{ t('transactions.index.actionsMenu.label') }}
                        <ChevronDown class="ml-1 h-4 w-4" aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem @select="openCreateDialog">
                        <Plus />
                        {{ t('transactions.index.addTransaction') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="route('transfers.create') + currentSearch">
                            <ArrowRightLeft />
                            {{ t('transactions.index.addTransfer') }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem @select="importDialogOpen = true">
                        <Upload />
                        {{ t('imports.cta') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>
```

- [ ] **Step 2: Prepend filters in the page column**

In the same file, inside `<div class="flex flex-col gap-6">`, insert the filter component **before** `ImportFailedRowsBanner`. Do not move or restyle the banners.

```vue
        <div class="flex flex-col gap-6">
            <TransactionsIndexHeaderFilters
                :accounts="accounts"
                :categories="categories"
                :filters="filters"
                :server-errors="serverErrors"
                :is-loading="isLoading"
            />

            <ImportFailedRowsBanner
```

Keep the import of `TransactionsIndexHeaderFilters` (already at the top of the script).

- [ ] **Step 3: Left-align the filter root**

In `resources/js/components/transactions/TransactionsIndexHeaderFilters.vue`, replace the root `class` on the `role="search"` div.

From:

```
class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"
```

To:

```
class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
```

Do not change `DropdownSelect`, cash-gradient leading icons, `DateRangePickerInput` usage, debounce, telemetry, or the error `InputError` block.

- [ ] **Step 4: Format and typecheck**

```bash
npx prettier --write resources/js/pages/transactions/Index.vue resources/js/components/transactions/TransactionsIndexHeaderFilters.vue
npm run typecheck
```

Expected: Prettier rewrites those two files. Typecheck: no **new** errors in those two files.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/transactions/Index.vue resources/js/components/transactions/TransactionsIndexHeaderFilters.vue
git commit -m "$(cat <<'EOF'
feat(transactions): move index filters onto the page floor

EOF
)"
```

---

### Task 2: Quiet summary cards

**Files:**
- Modify: `resources/js/pages/transactions/Index.vue`

**Interfaces:**
- Consumes: existing `summaryIncome`, `summaryExpense`, `summaryCurrencySymbol`, `isLoading`.
- Produces: two `rounded-2xl border-transparent bg-card` cards; expense amount `red` not `rose`.

- [ ] **Step 1: Replace summary markup**

In `resources/js/pages/transactions/Index.vue`, replace the summary `grid` (the block that currently uses `rounded-xl border border-sidebar-border/70`) with:

```vue
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-transparent bg-card p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        {{ t('transactions.index.summary.income') }}
                    </p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            +{{ summaryIncome }} {{ summaryCurrencySymbol }}
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-transparent bg-card p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        {{ t('transactions.index.summary.expense') }}
                    </p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-red-600 dark:text-red-400">
                            -{{ summaryExpense }} {{ summaryCurrencySymbol }}
                        </p>
                    </div>
                </div>
            </div>
```

Leave the list card, empty states, banners, and dialogs untouched. Grep this file: `rose` must not appear on the summary expense line (list rows already use `red` in `TransactionRow`).

- [ ] **Step 2: Format and typecheck**

```bash
npx prettier --write resources/js/pages/transactions/Index.vue
npm run typecheck
```

Expected: Prettier rewrite. No new type errors in `Index.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/transactions/Index.vue
git commit -m "$(cat <<'EOF'
style(transactions): lift summary cards to OpenShip tokens

EOF
)"
```

---

### Task 3: Date picker and Add menu tokens

**Files:**
- Modify: `resources/js/components/forms/DateRangePickerInput.vue`
- Modify: `resources/js/pages/transactions/Index.vue`

**Interfaces:**
- Consumes: existing `DateRangePickerInput` props (`from` / `to` DD-MM-YYYY strings, `change` emit) and the Add `DropdownMenu` from Task 1.
- Produces: same date-range behavior; OpenShip radius/hover on picker chrome; Add menu panel `rounded-2xl`.

- [ ] **Step 1: Date picker trigger clear control**

In `resources/js/components/forms/DateRangePickerInput.vue`, keep the trigger `h-10` (must stay aligned with `DropdownSelect`). Replace the clear-button `:class` `cn(...)` with:

```vue
                        :class="
                            cn(
                                'inline-flex h-7 w-7 items-center justify-center rounded-xl',
                                isClearDisabled ? 'cursor-not-allowed opacity-40' : 'hover:bg-foreground/[0.06]',
                            )
                        "
```

- [ ] **Step 2: Date picker popover panel**

Same file, on `PopoverContent`, change `class="p-3"` to `class="rounded-2xl p-3"`. Do not edit `components/ui/popover/PopoverContent.vue`.

```vue
        <PopoverContent class="rounded-2xl p-3" align="start">
```

- [ ] **Step 3: Date picker preset buttons**

Same file, all five preset `<button>` elements currently use:

```
class="w-full rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
```

Replace **each** of those five `class` values with:

```
class="w-full rounded-xl border px-3 py-2 text-left text-sm hover:bg-foreground/[0.06]"
```

The five handlers stay: `applyPresetThisMonth`, `applyPresetPreviousMonth`, `applyPresetThisYear`, `applyPresetPreviousYear`, `applyPresetLast7Days`. Do not change calendar logic or i18n keys.

- [ ] **Step 4: Add menu panel radius**

In `resources/js/pages/transactions/Index.vue`, change the Add `DropdownMenuContent` class from `w-56` to `w-56 rounded-2xl`:

```vue
                <DropdownMenuContent align="end" class="w-56 rounded-2xl">
```

Do not edit `components/ui/dropdown-menu/DropdownMenuContent.vue`. Items and handlers stay.

- [ ] **Step 5: Format and typecheck**

```bash
npx prettier --write resources/js/components/forms/DateRangePickerInput.vue resources/js/pages/transactions/Index.vue
npm run typecheck
```

Expected: Prettier rewrite. No new type errors in those two files.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/forms/DateRangePickerInput.vue resources/js/pages/transactions/Index.vue
git commit -m "$(cat <<'EOF'
style(transactions): round date-range picker and Add menu to OpenShip

EOF
)"
```

---

### Task 4: Smoke tests, checklist, browser check

**Files:**
- Modify: `.docs/checklist.md`
- Test: `tests/Feature/Transactions/TransactionIndexTest.php`
- Test: `tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php`

**Interfaces:**
- Consumes: Tasks 1–3 UI.
- Produces: checklist note; passing index + filter-persistence Pest.

- [ ] **Step 1: Run Pest smoke**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionIndexTest.php tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php
```

Expected: PASS (no PHP changes; Inertia still `transactions/Index`; filters still query-backed).

- [ ] **Step 2: Checklist**

Under `.docs/checklist.md` §4 “Lista transakcji”, add a checked item **after** the existing OpenShip list line:

```markdown
  - [x] Lista OpenShip: karta wierszy, empty na podłodze, pager **[plan 2026-08-26-transactions-index-openship]**
  - [x] Chrome OpenShip: filtry na podłodze, ciche karty podsumowania, menu Dodaj **[plan 2026-08-26-transactions-index-chrome-openship]**
```

Do not edit PRD (visual-only).

- [ ] **Step 3: Browser verification**

Resolve the app URL with Boost `get-absolute-url` (do not assume host `:80` is Sail). Log in if needed (`test@example.com` / `password`). Exercise:

1. `/transakcje` header: title + Add dropdown only (no filters in the header).
2. Filters on the grey floor, left-aligned, **above** banners/summary; not inside a card.
3. Two summary cards: `rounded-2xl`, no visible hairline border, emerald income, **red** (not rose) expense.
4. Change account and date range: list still filters; empty “clear filters” still works.
5. Add menu: transaction opens form dialog; transfer visits `transfers.create`; import opens `ImportDialog`.
6. Dark mode: summary cards lift off the black page; amounts still emerald/red.
7. Viewport `< md`: filters stack, then summary cards stack, then mobile rows.
8. List card, empty floor, pager, and banners look as they did before this plan.

- [ ] **Step 4: Commit checklist**

```bash
git add .docs/checklist.md
git commit -m "$(cat <<'EOF'
docs: record OpenShip transactions index chrome restyle

EOF
)"
```

---

## Self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| Filters leave `#headerActions`; Add dropdown stays | Task 1 |
| Filters first in `gap-6` column; banners stay above summary | Task 1 |
| HeaderFilters drop `flex-1` / `justify-end`; wrap + start | Task 1 |
| `DropdownSelect` and cash gradient untouched | Task 1 (explicit) |
| Two quiet `rounded-2xl bg-card` summary cards | Task 2 |
| Label 11px uppercase tracking; expense `red` not `rose` | Task 2 |
| `DateRangePickerInput` trigger stays `h-10` | Task 3 |
| Clear `rounded-xl` + `hover:bg-foreground/[0.06]` | Task 3 |
| Presets `rounded-xl` + same hover; popover `rounded-2xl` | Task 3 |
| Add `DropdownMenuContent` `rounded-2xl` at call site | Task 3 |
| No PHP / no new Vue files / no `ui/` primitive edits | All |
| Pest smoke + filter persistence + browser | Task 4 |
| Checklist §4 | Task 4 |

No TBD/TODO in tasks. Prop names (`accounts`, `categories`, `filters`, `serverErrors`, `isLoading`) are unchanged across tasks.

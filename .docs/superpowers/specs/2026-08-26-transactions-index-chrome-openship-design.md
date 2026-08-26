# Transactions index chrome OpenShip — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** OpenShip restyle of always-visible `/transakcje` chrome: page-header Add menu, filter bar, income/expense summary cards.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-transactions-index-openship-design.md` (list, empty, pager already shipped).  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (surface ladder, quiet cards, page header); `/konta` header (title + one primary action) as the chrome reference.

This spec **supersedes** the list spec’s “summary cards and header filters stay as they are”.

## Goal

Make the rest of the transactions index read as the same quiet operator UI as the list and `/konta`: grey floor, header = title + one ink action, filters as a floor row, two lifted summary cards with color only on the money. No PHP, query, or modal behavior changes.

## Decisions log

| Topic | Decision |
|-------|----------|
| Slice | **A:** Summary cards + filters + Add menu. Banners and modal interiors later |
| Filter placement | **B:** Below the title on the grey floor, above the rest of the page body. Header keeps only Add |
| Summary cards | **A:** Two quiet `rounded-2xl bg-card` cards; emerald / **red** (not rose) on amounts only |
| Add menu | **A:** Keep the existing dropdown (three items); tokens only |
| Filter primitives | **A:** Do not restyle global `DropdownSelect`. `DateRangePickerInput` may change (transactions-only) |
| Implementation | **1:** Relocate existing filter component; restyle summary in `Index.vue`; no new chrome components |

## Current state

- `PageHeader` `#headerActions` holds `TransactionsIndexHeaderFilters` **and** the Add `DropdownMenu` in one cramped row (`sm:justify-end`).
- Filters: account `DropdownSelect`, category `DropdownSelect`, `DateRangePickerInput`. Debounce 300 ms, `transactions_filtered`, session persistence via existing query. Root classes include `flex-1` and `sm:justify-end` (header fill). Cash account leading icons use an amber→orange gradient.
- `DateRangePickerInput` is used **only** here. Trigger is outline `h-10`; clear control `rounded` + `hover:bg-muted`; preset buttons `rounded-md`. Popover panel is default shadcn `rounded-md`.
- Summary: two `rounded-xl border border-sidebar-border/70 p-6` boxes. Income emerald; expense **rose**. Labels `text-xs text-muted-foreground`.
- Add trigger is already the shared ink `Button` (`rounded-xl`). `DropdownMenuContent` is `class="w-56"` on a primitive that defaults to `rounded-md`.
- Body order today: import-failed banner → transfer-candidate banner → summary → list/empty/pager (list already OpenShip).
- Add/Edit/Import/Delete dialogs already open from this page. Out of this spec.

## Target behavior

No PHP, routes, Inertia props, i18n keys, or telemetry events change. Filter apply/clear, sort, pagination, and dialog open paths stay as they are.

### Surface composition

```
PageHeader: title “Transakcje”              [ Add ▾ ]

filters on grey floor     (account · category · dates)   ← moved from header
banners                   (unchanged, still above summary)
summary cards             two quiet cards, md: 2-col
list / empty / pager      already OpenShip
```

**Do not reorder banners vs summary.** Prepend the filter bar as the first child of the existing `flex flex-col gap-6` column.

`#headerActions` contains **only** the Add dropdown (same three items: create modal, `Link` to `transfers.create` + `currentSearch`, open `ImportDialog`).

`TransactionsIndexHeaderFilters` keeps its name, `role="search"`, `aria-label`, and control ids (`account_id`, `category_id`, `date_range`). Moving the landmark from the header onto the page is intended.

### Filter bar

On the floor: **no** wrapping card.

| | Today (header) | Target (page) |
|--|----------------|---------------|
| Root | `flex flex-1 … sm:justify-end` | Drop `flex-1`. Align start: `flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center` |
| Control widths | `min-w-56 sm:min-w-64` | Unchanged |
| `DropdownSelect` | outline `h-10` | **Unchanged** (shared primitive) |
| Cash / category leading icons | gradient / `CategoryBadge` | Unchanged (gradient lives in the form dialog too) |
| Date errors | `InputError` + `aria-live` | Unchanged |

`DateRangePickerInput` (this file only):

- Trigger stays `h-10` so the row matches `DropdownSelect` (do **not** bump to OpenShip input `h-11`).
- Clear control: `rounded-xl`; hover `hover:bg-foreground/[0.06]` (replace `rounded` + `hover:bg-muted`).
- Preset buttons: `rounded-xl`; hover `hover:bg-foreground/[0.06]` (replace `rounded-md` + `hover:bg-muted`).
- `PopoverContent`: add `rounded-2xl` at this call site. Do not change `ui/popover`.
- Preset logic, range selection, and i18n: unchanged.

### Summary cards

Keep the two-card `grid gap-4 md:grid-cols-2` in `Index.vue` (no `TransactionsSummaryCards` extract).

Each card: `rounded-2xl border border-transparent bg-card p-6`, no resting shadow.

| Piece | Classes |
|-------|---------|
| Label | `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60` (existing `summary.income` / `summary.expense` keys) |
| Amount | `text-2xl font-semibold tabular-nums` |
| Income | `text-emerald-600 dark:text-emerald-400` |
| Expense | `text-red-600 dark:text-red-400` (**replace `rose`**) |
| Loading | existing `Skeleton` `h-8 w-40` |

Sign prefixes (`+` / `-`) and `summaryCurrencySymbol` stay.

### Add menu

Trigger: existing default `Button` + Plus + label + ChevronDown. No extra shadow.

`DropdownMenuContent`: keep `align="end" class="w-56"` and add `rounded-2xl` on **this** call site only. Do not change `ui/dropdown-menu`.

Items, icons, and handlers unchanged.

## Data flow

Unchanged: `TransactionIndexRequest` → `ListTransactions` → `TransactionController::index` → Inertia `transactions/Index`. Filters still `router.get` with `preserveScroll` / `replace` / `preserveState: 'errors'`.

## Files

| File | Change |
|------|--------|
| `resources/js/pages/transactions/Index.vue` | Filters out of `#headerActions`; Add-only header; summary card tokens; Add menu `rounded-2xl` |
| `resources/js/components/transactions/TransactionsIndexHeaderFilters.vue` | Left-align floor layout (drop `flex-1` / `justify-end`) |
| `resources/js/components/forms/DateRangePickerInput.vue` | Radius + hover tokens; popover `rounded-2xl` at call site |

Do not add PHP Actions/Resources. Do not add new Vue files. Do not edit `DropdownSelect.vue` or `components/ui/*` primitives.

## Testing

| Check | Method |
|-------|--------|
| Index + filters still persist and apply | Existing Pest `TransactionIndexTest`, `TransactionsIndexFiltersPersistenceTest` as smoke |
| No new i18n keys | Grep / compile |
| Visual: header, filter row, two cards, dark, mobile stack | Manual (browser) |
| Add → modal, transfer → page, import → dialog | Manual |

No new Pest test for CSS classes. After Vue: Prettier. No Pint/PHPStan unless PHP is touched (none expected).

## Out of scope

- Import failed-rows banner and transfer-candidate banner (stay above summary, visually untouched)
- `TransactionFormDialog`, `DeleteTransactionDialog`, `ImportDialog` interiors
- `transfers.create` page
- Global `DropdownSelect` / `ui/button` / `ui/dropdown-menu` / `ui/popover` primitives
- Cash icon gradient
- Extracting `TransactionsSummaryCards` or renaming `TransactionsIndexHeaderFilters`
- List, empty states, pagination (already shipped)
- Column set, filter fields, or query persistence

## Implementation notes (for plan)

1. Move the filter component in `Index.vue` first so header/page layout can be checked before card class churn.
2. Replace `rose` with `red` **only** on the summary expense amount (list rows already use `red`).
3. `DateRangePickerInput` is safe to restyle in place: grep shows no other Vue consumers.
4. After Vue: Prettier. After any PHP (none expected): Pint + Sail `--filter=Transactions`.
5. Verify in the browser: desktop chrome, mobile stack, dark mode, filter apply, Add menu three destinations.

## Success criteria

- Header matches `/konta`: title + one primary control (the Add dropdown).
- Filters sit on the grey floor, left-aligned, not inside a card, not in the header.
- Two summary cards are borderless `rounded-2xl bg-card`; expense uses `red`, not `rose`.
- Filter debounce, persistence, empty-state “clear filters”, and Add/transfer/import still work.
- Banners and modal interiors are visually untouched.

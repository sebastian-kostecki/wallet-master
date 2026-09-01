# Budget pages OpenShip — design spec

**Date:** 2026-08-27  
**Status:** Approved in brainstorming  
**Scope:** OpenShip restyle of `/budget` monthly and yearly **content**: period control in the page header; lifted cards — summary, P&L categories, and pockets (monthly only when rows exist); OpenShip tokens on progress chips, summary tiles, and plan-edit icons.  
**Depends on:** `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md` (`PageHeader`, budget tabs); `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md` (columns, progress semantics, inline plan edit).  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md`; `/kategorie` list card as the section-label reference.

**Hard constraint:** The summary must stay a **separate card** with its **own table**. Do not put Przychody / Wydatki / Saldo rows in the category `<table>`, do not share a `<tbody>` with category rows, and do not revive the discarded one-P&L-table layout.

## Goal

Make monthly and yearly budget pages read as the same quiet operator UI as `/konta` and `/kategorie`: grey page floor, borderless lifted cards, ink chrome, color only on money and status. The period P&L totals must stay visually distinct from category rows.

PHP, routes, Inertia props, and plan-edit behavior stay unchanged.

## Decisions log

| Topic | Decision |
|-------|----------|
| Summary vs categories | **A:** Two cards — summary first, then categories. Never one table. |
| Remaining surfaces | **C:** One P&L card (income + expense), pockets as a third card (monthly only) |
| Section labels in P&L card | **A:** OpenShip `11px` uppercase labels; one `thead` at the top of the card |
| Period nav | **A:** `#headerActions` (`BudgetPeriodNav`); no period row in the body |
| Implementation | **A:** Restyle + page composition. Cards stay `<section>` components, not `tbody`-root extracts |
| Views in this spec | Monthly **and** yearly |

## Current state

- `PageHeader` + Monthly / Yearly tabs are already OpenShip (`pageChrome`).
- Body chrome is leftover: `rounded-xl`, `shadow-sm`, `border-sidebar-border/70`, `text-lg` section titles, period `←` / `→` in the page body.
- Four frames on monthly: `BudgetSummaryCard`, income `BudgetCategorySection`, expense `BudgetCategorySection`, `BudgetPocketSection`.
- Summary uses `bg-muted/30` and a table with the same column grid as categories (`BudgetTableColgroup` + `--budget-col-*` on `.budget-page`).
- Progress chips use solid fills (`emerald-100` / `red-100`). Summary balance tile is indigo; expense tile is rose.
- Plan-edit icons (`EditableEstimateCell`, `YearlyPlanEditCell`) do not set Lucide `stroke-width="1.7"`.
- Pockets exist only on monthly; the block is hidden when `pocket_rows.length === 0`.

## Target behavior

### Surface composition

```
PageHeader
  Budżet                         [ ←   8 / 2026   → ]
  tabs: Monthly | Yearly         (unchanged)

grey floor, flex flex-col gap-6

┌─ summary card  rounded-2xl border-transparent bg-card ──────────┐
│  thead: (blank) | Plan | [Forecast] | Actual | Progress %        │
│  Income / Expenses / Balance                                     │
└─────────────────────────────────────────────────────────────────┘

┌─ P&L categories card ───────────────────────────────────────────┐
│  thead: Name | Plan | [Forecast] | Actual | Progress %           │
│  INCOME     (OpenShip section label, colspan)                    │
│  category rows (editable plan)                                   │
│  EXPENSES                                                        │
│  category rows                                                   │
└─────────────────────────────────────────────────────────────────┘

┌─ pockets card (monthly only, if rows.length > 0) ───────────────┐
│  ENVELOPES                          Manage envelopes →           │
│  thead: Name | Plan | Movement | Progress %                      │
│  pocket rows                                                     │
└─────────────────────────────────────────────────────────────────┘
```

Yearly: summary + P&L cards only (no pockets card). Forecast column stays on yearly tables, same as today.

**Floor:** `bg-background` from the app shell. Cards sit on it with `gap-6`. No extra wrappers, no resting shadows.

**Column alignment:** Keep `.budget-page` CSS variables and `BudgetTableColgroup`. Summary and P&L are **two tables** that share the same column widths, not one table.

### Period nav (`BudgetPeriodNav`)

New `resources/js/components/budget/BudgetPeriodNav.vue`, slotted into `#headerActions` on both pages.

| Aspect | Decision |
|--------|----------|
| Controls | Ghost `Button` `size="icon"` `rounded-xl`, `hover:bg-foreground/[0.06]` |
| Icons | Lucide `ChevronLeft` / `ChevronRight`, `size-4`, `stroke-width="1.7"`, `aria-hidden` |
| Label | Monthly: `{{ month }}/{{ year }}`; yearly: `{{ year }}` — `text-sm font-medium tabular-nums` |
| A11y | `aria-label` from new keys `budget.period.previous` / `budget.period.next` |
| Navigation | Existing `router.get` to `budget.monthly` / `budget.yearly` |

Remove the body period row from `Monthly.vue` and `Yearly.vue`. Do not change `pageChrome` tabs.

### Cards (shared chrome)

| Element | Classes |
|---------|---------|
| Card | `rounded-2xl border border-transparent bg-card p-5 sm:p-6` — no `shadow-sm` |
| `thead` | `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60` |
| Row hover | `hover:bg-foreground/[0.03]` (or existing `.list-row-hover` if equivalent) |
| Dividers | `border-border/50` |
| Money | `tabular-nums` |

### Summary card (`BudgetSummaryCard`)

Stays a `<section>` with its **own** `<table>` and `thead`.

- First header cell stays **empty** (row labels are Income / Expenses / Balance). Category card’s first header is Name. Widths still match via colgroup.
- Keep props: `summary`, `currency`, `variant`.
- `BudgetSummaryRowLabel` tiles: `rounded-lg`; income emerald; expense red (`text-red-600` / `dark:text-red-400`, `bg-red-500/10`); balance zinc (not indigo). Drop hardcoded indigo / rose fills.
- No new summary data, no KPI-tile rewrite.

### P&L categories card (`BudgetCategorySection`)

Refactor to **one card, both groups**. Pages pass `incomeRows` and `expenseRows` once (stop mounting the component twice).

One `<table>`:

1. `thead` (Name, Plan, Forecast if yearly, Actual, Progress %).
2. Income group: a header row with `colspan` equal to column count, styled as OpenShip label (`text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`), copy `budget.sections.income`.
3. Income category rows (same cells as today).
4. Expense group: same label pattern, copy `budget.sections.expense`.
5. Expense category rows.

Empty group: render the label and **zero** data rows. No floor empty state, no new i18n keys.

Edit flow unchanged: one row at a time, PATCH `categories.estimates.monthly` / `categories.estimates.yearly-plan`, Enter/Esc, monthly align-to-actual. Input `rounded-xl`; ghost icon buttons `hover:bg-foreground/[0.06]`.

Do **not** extract `tbody`-only components or a shared `BudgetPnlHead` used by summary.

### Pockets card (`BudgetPocketSection`)

Own card, monthly only. Hidden when `rows.length === 0`.

- Title: existing `budget.monthly.pockets_section`, OpenShip section-label typography (not `text-lg`).
- Manage link: existing `budget.monthly.manage_pockets` → `pockets.index`. Quiet `Button variant="ghost"` (or equivalent muted text), not `variant="link"`.
- Own table and `thead` (Name, Plan, Movement, Progress). Movement column stays; do not force P&L Actual onto this table.

### Progress chips (`BudgetProgressCell`)

Tone logic unchanged (income &lt; 100% bad; expense &lt; 100% good, 100% warn, &gt; 100% bad; `null` → `—`).

Visual recipe:

```
rounded-full border px-2.5 py-0.5 text-xs font-medium tabular-nums
good:  border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400
warn:  border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400
bad:   border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400
```

### Plan-edit icons

In `EditableEstimateCell` and `YearlyPlanEditCell`, set Lucide `stroke-width="1.7"` on Pencil / Check / X / ArrowRightLeft. No change to validation or PATCH payloads.

## Data flow

Unchanged:

```
GET budget.monthly / budget.yearly
  → Budget controllers + List* Actions
  → Inertia budget/Monthly | budget/Yearly
      { year, month?, rows, pocket_rows?, summary, currency }
```

Frontend still splits `rows` into income / expense, owns `editingCategoryId` / `editingMode`, and patches estimates.

## Files

| File | Change |
|------|--------|
| `resources/js/components/budget/BudgetPeriodNav.vue` | **New** — period control |
| `resources/js/pages/budget/Monthly.vue` | `#headerActions`; one `BudgetCategorySection`; remove body period row |
| `resources/js/pages/budget/Yearly.vue` | Same without pockets |
| `resources/js/components/budget/BudgetSummaryCard.vue` | OpenShip card + thead tokens |
| `resources/js/components/budget/BudgetCategorySection.vue` | One card, both groups, one thead, section-label rows |
| `resources/js/components/budget/BudgetPocketSection.vue` | OpenShip card, label, ghost manage link |
| `resources/js/components/budget/BudgetProgressCell.vue` | Status-token chips |
| `resources/js/components/budget/BudgetSummaryRowLabel.vue` | Tile radius + hues |
| `resources/js/components/budget/EditableEstimateCell.vue` | Icon stroke 1.7 |
| `resources/js/components/budget/YearlyPlanEditCell.vue` | Icon stroke 1.7 |
| `resources/js/locales/en.json`, `pl.json` | `budget.period.previous`, `budget.period.next` |

Do not add PHP Actions/Resources. Do not change `BudgetTableColgroup` column roles unless a Vue bug requires it.

## Testing

| Check | Method |
|-------|--------|
| Monthly / yearly still render, estimates still PATCH | Existing Pest `MonthlyBudgetTest`, `YearlyBudgetTest` |
| Progress / forecast / summary math | Existing `tests/Unit/Support/Budgets/*` |
| Tabs / chrome | Existing `pageChrome.test.ts` (no change expected) |
| Touched Vue types | `npm run typecheck` — ignore pre-existing errors outside this spec’s files |
| Visual: two (yearly) / three (monthly) cards, summary not mixed into categories, period in header, dark, mobile overflow | Manual (browser) |

No new Pest tests for CSS classes. No new browser tests.

After implementation: tick the OpenShip budget restyle checkbox in `.docs/checklist.md` (budget views / UX section).

## Out of scope

- PHP, routes, Inertia prop shapes, estimate APIs
- Merging summary rows into the category table
- `tbody`-root refactor (`BudgetSummaryRows`, `BudgetPnlHead`, shared thead across cards)
- KPI-tile summary (three big number cards)
- Global `ui/button`, `ui/input`, `PageHeader`, `pageChrome` tabs
- Dashboard or other index restyles
- Pocket CRUD / `/kieszenie` list (already OpenShip)
- New empty-state copy

## Implementation notes (for plan)

1. Add `BudgetPeriodNav` and wire `#headerActions` first so body period buttons can go.
2. Restyle chips and summary tiles (shared by all three cards) before composing the P&L card.
3. Change `BudgetCategorySection` to accept both row lists in one card; update both pages together.
4. Restyle `BudgetSummaryCard` and `BudgetPocketSection` as sibling cards — never wrap them in the P&L `<table>`.
5. After Vue: Prettier. Pint only if PHP is touched (not expected).
6. Verify in browser: monthly (with and without pockets), yearly, period next/prev, inline plan edit, dark mode, narrow viewport horizontal scroll.

## Success criteria

- Period control lives in the page header; the body is only cards.
- Summary is its own lifted card; its three rows are not in the category table.
- Income and expense share one P&L card, one thead, OpenShip section labels.
- Pockets are a separate card on monthly when rows exist.
- Surfaces use `rounded-2xl border-transparent bg-card` with no resting shadow.
- Progress chips and summary tiles use OpenShip status tokens.
- Plan edit, PATCH, tabs, and Inertia props behave as today.

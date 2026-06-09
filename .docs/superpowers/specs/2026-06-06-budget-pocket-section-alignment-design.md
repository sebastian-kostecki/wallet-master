# Budget monthly — pocket section alignment — design spec

**Status:** Approved in brainstorming (2026-06-06)  
**Builds on:** `.docs/prd.md` (FR-C5, FR-P5), `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md`, `.docs/superpowers/specs/2026-06-06-budget-pocket-summary-design.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `resources/js/pages/budget/Monthly.vue`, `resources/js/components/budget/*`, `app/Actions/Budgets/ListMonthlyBudget.php`, `tests/Feature/Budgets/MonthlyBudgetTest.php`

## Summary

Align the monthly budget **pockets section** with income/expense category tables: shared column grid (Plan under Plan), a single **movement** column showing saved and released amounts together, and **monthly progress** (saved vs plan). Remove cumulative target hints and the separate balance column from the table. Hide the entire section when the user has no active pockets.

## Problem

The pockets section in `Monthly.vue` uses a custom inline table with five columns (Plan, Odłożono, Wypłacono, Saldo) and no progress column. It does not share `BudgetTableColgroup`, so the Plan column does not align with P&L sections above. `progress_percent` in pocket rows reflects cumulative progress toward `target_amount`, not monthly plan execution — inconsistent with category rows and the redesigned budget UX.

## Decisions log

| Topic | Decision |
|-------|----------|
| Column layout | **B — Plan \| Ruch w miesiącu \| Postęp** (4 columns total incl. name), same grid widths as monthly category tables |
| Movement cell | Stacked: `+ odłożono` / `− wypłacono` in one column |
| Monthly balance | **Hidden** — saved and released lines are sufficient; no `= saldo` line |
| Progress semantics | **Monthly:** `saved` vs `monthly_plan` (same as P&L execution %); tone via `categoryType="expense"` |
| Cumulative target hint | **Removed** — no `balance_cumulative / target_amount` subtext under plan |
| Plan editing | **Read-only** on budget view (edit on `/pockets/{id}/edit` per FR-P2) |
| Empty state | **Hide section** when `pocket_rows.length === 0` |
| Section header | Title + link **Zarządzaj kieszeniami** → `/pockets` (FR-P5; locale `budget.monthly.manage_pockets`) |
| Summary card | **Unchanged** — `BudgetSummary::withPockets()` already merges pocket flows (see pocket-summary spec) |
| Pocket metrics | **Unchanged** — `saved`, `released`, `balance` still computed; only UI/API shape trimmed |
| API payload | Remove unused display fields from Inertia props: `balance`, `balance_cumulative`, `target_amount` |
| Component approach | **A — new `BudgetPocketSection`** + `BudgetPocketMovementCell`; extend `BudgetTableColgroup` with `layout="pocket"` |
| Yearly budget | **Out of scope** — no pocket section on yearly view |

## Information architecture

### Monthly (`/budget/monthly`) — pockets block

```
[Section: Pockets — only if pocket_rows.length > 0]
  Header: "Kieszenie oszczędnościowe" + link "Zarządzaj kieszeniami"
  Table (shared colgroup with income/expense):
    | Nazwa | Plan | Ruch w miesiącu | Postęp |
```

Section order unchanged: Summary → Income → Expenses → Pockets.

## UI design

### Column definitions

| Column | Content |
|--------|---------|
| Name | `PocketBadge` (icon, color, name) |
| Plan | Read-only `formatMoney(monthly_plan, pocket.currency)`; display `—` when null |
| Movement | `BudgetPocketMovementCell`: two lines with muted labels (`budget.monthly.saved` / `released`) and tabular amounts |
| Progress | `BudgetProgressCell` with `percent` and `categoryType="expense"` |

### Movement cell layout

```
+ odłożono   500,00 zł
− wypłacono    0,00 zł
```

Labels use existing i18n keys. Amounts use each pocket's currency (MVP: PLN).

### Progress rules

- Formula: `BudgetProgress::percent(monthly_plan, saved)` — same helper as P&L rows.
- `monthly_plan` null or zero → display `—`.
- Color semantics match expenses: below plan = green, at plan = yellow (100%), above = red.

### Grid alignment

- Reuse CSS variables from `.budget-page` in `Monthly.vue` (`--budget-col-label`, `--budget-col-plan`, etc.).
- `BudgetTableColgroup` new variant `layout="pocket"` with `period="monthly"`: 4 columns identical to `layout="category"` monthly (label, plan, amount, progress).
- Movement column uses the `budget-col-amount` width slot (same as Execution on category tables).

## Backend design

### `ListMonthlyBudget::buildPocketRows()`

Change progress calculation:

```php
'progress_percent' => BudgetProgress::percent(
    PocketPlanningProjection::monthlyPlanForBudget($pocket, $cumulative['balance']),
    $metrics['saved'],
),
```

Remove from row payload (no longer rendered):

- `balance`
- `balance_cumulative`
- `target_amount`

Keep: `pocket_id`, `name`, `icon`, `color`, `monthly_plan`, `saved`, `released`, `progress_percent`, `currency`.

`PocketBalance::progressPercent()` remains for `/pockets` index; only budget monthly row shape changes.

### Controller / Resource

If a `BudgetPocketResource` exists or pocket rows are mapped in controller, align with trimmed shape. No new routes or Actions.

## Frontend components

| File | Action |
|------|--------|
| `components/budget/BudgetPocketSection.vue` | **Create** — section wrapper, header with manage link, table body |
| `components/budget/BudgetPocketMovementCell.vue` | **Create** — stacked saved/released display |
| `components/budget/BudgetTableColgroup.vue` | **Extend** — `layout: 'summary' \| 'category' \| 'pocket'` |
| `pages/budget/Monthly.vue` | **Replace** inline pockets `<section>` with `<BudgetPocketSection>` |
| `locales/pl.json`, `locales/en.json` | **Add** `budget.monthly.movement` column header |

`BudgetPocketSection` props:

```ts
pocketRows: PocketRow[];
editingCategoryId: not used — no edit state
```

No estimate editing state for pockets on this page.

## i18n

| Key | PL | EN |
|-----|----|----|
| `budget.monthly.movement` | Ruch w miesiącu | Monthly movement |

Reuse: `budget.monthly.pockets_section`, `budget.monthly.manage_pockets`, `budget.monthly.plan`, `budget.monthly.saved`, `budget.monthly.released`, `budget.columns.progress`.

## Testing

### Feature — `tests/Feature/Budgets/MonthlyBudgetTest.php`

- Pocket row `progress_percent` reflects **saved vs monthly_plan**, not cumulative target progress.
- Example: pocket with `target_amount` 5000, cumulative balance 3200, monthly plan 500, saved 250 in month → `progress_percent` = 50 (not 64).
- Inertia props: `pocket_rows` entries omit `balance`, `balance_cumulative`, `target_amount`.

### Manual QA

1. Monthly budget with 2+ pockets — Plan column aligns vertically with income/expense Plan columns.
2. Pocket with saved and released in same month — both lines visible; no balance column.
3. Pocket without `target_amount` — progress shows saved/plan or `—` when no plan.
4. User with zero active pockets — pockets section not rendered.
5. Link „Zarządzaj kieszeniami” navigates to `/pockets`.

## Out of scope

- Yearly budget pocket section
- Inline plan editing on budget page
- Cumulative target progress in budget table
- Changes to `BudgetSummary::withPockets()` aggregation
- Pocket multi-currency display beyond existing per-pocket currency formatting

## References

- Reference layout: `BudgetCategorySection.vue` + `BudgetTableColgroup.vue`
- Metrics source: `PocketTransactionMetrics::forMonth()`, `PocketPlanningProjection::monthlyPlanForBudget()`
- PRD: FR-C5 (pocket section metrics), FR-P5 (per-pocket rows + manage link)

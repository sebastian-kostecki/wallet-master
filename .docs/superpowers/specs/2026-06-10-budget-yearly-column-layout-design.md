# Budget yearly — column order, forecast styling, balance colors — design spec

**Status:** Approved in brainstorming (2026-06-10)  
**Builds on:** `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md`, `.docs/superpowers/specs/2026-06-03-categories-budget-estimates-design.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `resources/js/pages/budget/Yearly.vue`, `resources/js/components/budget/BudgetSummaryCard.vue`, `resources/js/components/budget/BudgetCategorySection.vue`, `resources/js/components/budget/BudgetTableColgroup.vue`, `resources/js/lib/formatMoney.ts`

## Summary

Adjust the **yearly budget view** so the forecast column sits between plan and execution, appears visually secondary (muted text), and balance values in the summary card use signed colors (green positive, red negative, neutral zero).

## Problem

On `/budget/yearly`, column order is **Plan → Wykonanie → Prognoza → Postęp**. Forecast is a projection between plan and actual execution; placing it after execution breaks the mental model (plan → projected outcome → what happened so far). Forecast columns are styled identically to plan/execution, so they compete for attention. The balance row in the summary card shows unsigned amounts, making surplus vs deficit harder to scan.

## Decisions log

| Topic | Decision |
|-------|----------|
| Column order (yearly) | **Plan → Prognoza → Wykonanie → Postęp** |
| Monthly view | **Unchanged** — no forecast column |
| Forecast emphasis | **Muted color only** (`text-muted-foreground`); same font size (`text-sm`) |
| Forecast scope | Header + data cells in both summary card and category tables |
| Balance coloring | **All three numeric columns** in the balance row (plan, forecast, execution) |
| Positive balance | `text-emerald-600 dark:text-emerald-400` (matches transactions index) |
| Negative balance | `text-rose-600 dark:text-rose-400` (matches transactions index) |
| Zero balance | **Neutral** — no signed color class |
| Implementation approach | **A — minimal changes** in existing components + `signedMoneyClass()` helper in `formatMoney.ts` |
| New Vue component | **No** — avoid `BudgetMoneyCell` abstraction for this scope |
| Column config composable | **No** — not worth refactor for two tables |

## Information architecture

### Yearly (`/budget/yearly`)

```
Summary card:
  | (label) | Plan | Prognoza | Wykonanie | Postęp |
  Income row   ...
  Expense row  ...
  Balance row  ... (plan/forecast/execution cells colored by sign)

Category tables (income + expense):
  | Nazwa | Plan | Prognoza | Wykonanie | Postęp |
```

Prognoza column: muted foreground on header and all body cells.

## UI design

### Column reorder

Update template column order in:

- `BudgetSummaryCard.vue` — `<thead>` and all `<tbody>` rows when `variant === 'yearly'`
- `BudgetCategorySection.vue` — `<thead>` and category rows when `variant === 'yearly'`
- `BudgetTableColgroup.vue` — yearly colgroups: `label → plan → forecast → amount → progress`

Current yearly colgroup order (`plan → amount → forecast`) must match the new visual order (`plan → forecast → amount`).

### Forecast de-emphasis

Apply `text-muted-foreground` to:

- Forecast `<th>` in summary and category tables
- Forecast `<td>` values (still `tabular-nums`)

Do **not** change font size or weight.

### Balance signed colors

In `BudgetSummaryCard.vue`, balance row only (`budget.summary.balance`):

- `summary.plan.balance`
- `summary.forecast.balance` (yearly)
- `summary.execution.balance`

Each cell: `:class="signedMoneyClass(value)"` alongside `tabular-nums`.

Income and expense rows remain default foreground (no signed coloring).

### Helper: `signedMoneyClass`

Add to `resources/js/lib/formatMoney.ts`:

```ts
export function signedMoneyClass(value: string | number | null | undefined): string
```

Behavior:

1. Parse value the same way as `formatMoney` (comma/dot decimal, empty → neutral).
2. `NaN` → neutral (empty string).
3. `> 0` → emerald classes.
4. `< 0` → rose classes.
5. `=== 0` → neutral (empty string).

Reuse existing Tailwind tokens from `transactions/Index.vue` for consistency.

## Files to change

| File | Change |
|------|--------|
| `BudgetTableColgroup.vue` | Reorder `<col>` for yearly layouts |
| `BudgetSummaryCard.vue` | Column order, muted forecast, signed balance cells |
| `BudgetCategorySection.vue` | Column order, muted forecast |
| `formatMoney.ts` | Add `signedMoneyClass()` |
| `Yearly.vue` | No logic changes expected (CSS vars for col widths remain valid) |

## Testing

### Unit

- `signedMoneyClass`: positive, negative, zero, null/empty, comma decimal, invalid string

### Manual / feature (if existing yearly budget tests)

- Yearly page renders columns in order Plan → Prognoza → Wykonanie → Postęp
- Forecast header and values use muted styling
- Balance row: green for positive, red for negative, default for zero across plan/forecast/execution
- Monthly budget unchanged

## Out of scope

- Yearly pocket section (not present today)
- Signed colors on income/expense rows or category-level amounts
- Backend changes to forecast calculation
- Font-size reduction for forecast (user chose muted color only)

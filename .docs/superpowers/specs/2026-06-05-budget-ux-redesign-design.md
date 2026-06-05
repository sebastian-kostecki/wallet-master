# Budget views UX redesign — design spec

**Status:** Approved in brainstorming (2026-06-05)  
**Builds on:** `.docs/prd.md` (FR-C5, FR-C6, FR-UX1), existing budget Actions  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `resources/js/pages/budget/Monthly.vue`, `resources/js/pages/budget/Yearly.vue`, `app/Actions/Budgets/*`, `app/Support/Budgets/*`, `components/budget/*`

## Summary

Redesign monthly and yearly budget screens for clearer information hierarchy: summary card at top, income section before expenses, goals at bottom (monthly only). Replace the difference column with execution percentage and mirror color semantics (good/bad depends on category type). Add currency symbols to P&L amounts. Change estimate editing to explicit save (pencil → input + Save/Cancel). On yearly view, add per-category forecast and a third summary row aggregating forecasts.

## Decisions log

| Topic | Decision |
|-------|----------|
| Section order (monthly) | Summary → Income → Expenses → Goals |
| Section order (yearly) | Summary → Income → Expenses |
| Difference column | **Removed** — replaced by execution % |
| Progress colors | **Mirror semantics (A):** income below plan = warning/red, at/above = green; expense below plan = green, at/above = yellow (100%) / red (>100%) |
| Currency on P&L | Display with symbol via `formatMoney` (MVP: PLN from backend) |
| Estimate editing | **Pencil + explicit Save (B):** read-only amount + edit icon; expand input + Save/Cancel; one row editable at a time |
| Monthly summary layout | **One card, two rows (A):** Plan row + Execution row; columns Income / Expenses / Balance; section progress % under Income and Expenses |
| Yearly summary layout | **Extended (B):** same as monthly + third row **Forecast** (aggregated per section) |
| Yearly forecast formula | **A:** `actual_YTD + (annual_plan − sum(monthly_plans for months 1..M))` with monthly overrides via `CategoryPlanAmount::monthly` |
| Reference month M (yearly) | Current calendar month (inclusive) when viewing current year; `M = 12` for past years; `M = 0` for future years |
| No annual plan (forecast) | Forecast = actual YTD only |
| No plan (progress %) | Display `—` |
| `allocation_hint` | Keep below summary card on monthly view |
| Estimate API | Unchanged (`PATCH categories.estimates.monthly/annual`) |
| Architecture | Backend computes metrics (Action getters + `Support/Budgets/`); shared Vue components in `components/budget/` |

## Information architecture

### Monthly (`/budget/monthly`)

```
[View toggle] [Period nav]
[Summary card — Plan / Execution]
[allocation_hint]
[Section: Income — table]
[Section: Expenses — table]
[Section: Goals — table, unchanged metrics]
[Links: Categories / Goals]
```

### Yearly (`/budget/yearly`)

```
[View toggle] [Year nav]
[Summary card — Plan / Execution / Forecast]
[Section: Income — table]
[Section: Expenses — table]
[Link: Categories]
```

## Summary card

### Monthly — two rows

| | Income | Expenses | Balance |
|---|--------|----------|---------|
| **Plan** | Sum of monthly plans (income categories) | Sum of monthly plans (expense categories) | Plan income − plan expenses |
| **Execution** | Sum of actuals (income categories) | Sum of actuals (expense categories) | Execution income − execution expenses |

Below **Income** and **Expenses** columns: execution % for that section (`execution / plan × 100` when plan > 0, else `—`) with mirror colors.

### Yearly — three rows

| | Income | Expenses | Balance |
|---|--------|----------|---------|
| **Plan** | Sum of annual plans (income) | Sum of annual plans (expense) | Plan income − plan expenses |
| **Execution** | YTD actuals (income) | YTD actuals (expense) | Execution income − execution expenses |
| **Forecast** | Sum of per-category forecasts (income) | Sum of per-category forecasts (expense) | Forecast income − forecast expenses |

Section execution % shown under Plan/Execution (same rules as monthly). Forecast row has no separate progress badge.

All monetary cells use `formatMoney(value, currency)`.

## Category tables

### Monthly columns

| Column | Content |
|--------|---------|
| Category | `CategoryBadge` |
| Estimate | Read-only formatted amount; pencil opens edit mode |
| Execution | Formatted actual with currency |
| Execution % | Badge (+ optional thin bar) with mirror colors |

### Yearly columns

| Column | Content |
|--------|---------|
| Category | `CategoryBadge` |
| Estimate | Read-only + pencil edit (annual) |
| Execution | YTD actual with currency |
| Forecast | Per-category forecast with currency |
| Execution % | Badge with mirror colors |

### Execution % (per row)

- Formula: `round(actual / plan × 100)` when `plan > 0`
- Income: `< 100%` → red/warning; `≥ 100%` → green
- Expense: `< 100%` → green; `= 100%` → yellow; `> 100%` → red
- No plan or plan = 0 → `—`

## Estimate editing UX

**Default state:** formatted amount (or `—`) + pencil button (accessible label per category).

**Edit state (one row at a time):**
- Decimal input (same validation/normalization as today: comma/dot, empty = null)
- **Save** — `router.patch` to existing estimate endpoint; collapse on success
- **Cancel** — revert input, collapse
- Opening edit on another row closes the previous edit without saving

Monthly: `categories.estimates.monthly` with `year`, `month`, `amount`.  
Yearly: `categories.estimates.annual` with `year`, `amount`.

## Yearly forecast (per category)

```
forecast = actual_YTD + max(0, annual_plan − elapsed_plans_sum)
```

Where:
- `actual_YTD` — aggregated execution in calendar year (existing yearly actual logic)
- `annual_plan` — `CategoryPlanAmount::annual($annual)` or null
- `elapsed_plans_sum` — sum of `CategoryPlanAmount::monthly(...)` for months `1..M`
- `M` — reference month (see decisions log)
- If `annual_plan` is null: `forecast = actual_YTD`
- If `annual_plan − elapsed_plans_sum` is negative, treat remainder as `0` (forecast does not drop below YTD actual)

Example (May, annual 12 000, no overrides, actual YTD 4 200):
- Elapsed plans = 5 × 1 000 = 5 000
- Forecast = 4 200 + (12 000 − 5 000) = 11 200

## Backend changes

### `ListMonthlyBudget`

New getters:
- `getSummary(): array` — `plan` and `execution` each with `income`, `expense`, `balance` (decimal strings); `progress` with `income_percent`, `expense_percent` (int|null)
- `getCurrency(): array` — `{ code, symbol, precision }` (MVP: user's PLN)

Per row additions:
- `progress_percent: int|null`
- Remove `difference` from Inertia payload

### `ListYearlyBudget`

New getters:
- `getSummary(): array` — `plan`, `execution`, `forecast` sections (same shape as monthly + forecast row); `progress` for income/expense execution vs plan
- `getCurrency(): array`

Per row additions:
- `forecast: string`
- `progress_percent: int|null`
- Remove `difference` from Inertia payload

### New support classes

| Class | Responsibility |
|-------|----------------|
| `Support/Budgets/BudgetProgress` | Section and row execution percent; null when no plan |
| `Support/Budgets/BudgetForecast` | Per-category yearly forecast given year, reference month, annual/monthly estimates, actual YTD |
| `Support/Budgets/BudgetSummary` | Aggregate plan/execution/forecast totals from category rows |

`BudgetController` passes `summary` and `currency` to both Inertia pages.

No migration, no API route changes, no PRD FR identifier changes (UI refinement within FR-C5/C6).

## Frontend components

New under `resources/js/components/budget/`:

| Component | Purpose |
|-----------|---------|
| `BudgetSummaryCard.vue` | Summary table; props: `rows` (plan/execution/forecast), `currency`, `variant` (`monthly` \| `yearly`) |
| `BudgetCategorySection.vue` | Section heading + table wrapper for income/expense |
| `BudgetProgressCell.vue` | Percent badge + bar; props: `percent`, `categoryType` (`income` \| `expense`) |
| `EditableEstimateCell.vue` | Read/edit states, Save/Cancel, emits save with normalized amount |

Pages `Monthly.vue` / `Yearly.vue` become thin orchestrators (props → components, section order, period nav).

Reuse `formatMoney` from `@/lib/formatMoney` (same pattern as goals).

## i18n keys (add to `pl.json` / `en.json`)

- `budget.summary.plan`, `budget.summary.execution`, `budget.summary.forecast`
- `budget.summary.income`, `budget.summary.expense`, `budget.summary.balance`
- `budget.columns.progress`, `budget.columns.forecast`
- `budget.estimate.edit`, `budget.estimate.save`, `budget.estimate.cancel`

Remove or stop using `budget.monthly.difference` / `budget.yearly.difference` in templates (keys may remain for backwards compatibility or be removed in same PR).

## Error handling

- Save estimate: existing validation errors via Inertia flash/validation (unchanged)
- Invalid input on Save: inline, do not PATCH until valid decimal or empty (clear override)
- Summary with all null plans: show `—` for percents; sums treat null plans as 0 for aggregation only where category has actuals

## Testing

### Feature (`tests/Feature/Budgets/`)

- Monthly summary: correct plan/execution totals and balance for mixed categories
- Monthly row: `progress_percent` when plan set; `—` when no plan
- Yearly forecast in May: override vs `annual ÷ 12` elapsed sum
- Yearly summary: forecast row equals sum of category forecasts
- Inertia props: no `difference`; includes `summary`, `currency`, `forecast` (yearly)

### Unit (`tests/Unit/Support/Budgets/`)

- `BudgetForecast` — current year May, past year, future year, no annual plan, negative remainder clamped
- `BudgetProgress` — percent rounding, null plan

## Out of scope

- Goals section redesign (monthly goals table unchanged except page order)
- Multi-currency UI beyond PLN symbol
- Charts / graphs
- PRD text update (optional follow-up; behavior stays within FR-C5/C6)
- Yearly goals rollup

## Self-review checklist

- [x] No TBD / placeholder sections
- [x] Forecast formula matches user choice A
- [x] Colors match user choice A (mirror)
- [x] Edit UX matches user choice B
- [x] Summary layouts match user choices A (monthly) and B (yearly)
- [x] Scoped to single implementation plan (one PR)
- [x] Aligns with Variant A architecture (Actions + Support + components/budget)

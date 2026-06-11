# Budget yearly view — dual plan edit + independent monthly model — design spec

**Status:** Approved in brainstorming (2026-06-11)  
**Supersedes:** `.docs/superpowers/specs/2026-06-10-budget-yearly-dual-plan-edit-design.md` (draft; not implemented)  
**Builds on:** `.docs/superpowers/specs/2026-06-03-categories-budget-estimates-design.md`, `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md`, `.docs/superpowers/specs/2026-06-10-budget-plan-cell-actions-design.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `CategoryPlanAmount`, `BudgetForecast`, `ListMonthlyBudget`, `ListYearlyBudget`, `SaveYearlyCategoryPlan`, `YearlyPlanEditCell`, `Yearly.vue`, `BudgetCategorySection.vue`, routes, requests, tests, i18n, PRD §5/FR-C4 alignment

## Summary

1. **Yearly inline edit** — two side-by-side inputs: annual plan (primary yearly target) + optional monthly plan (bulk write to eligible future months).
2. **Display model change** — monthly plan in budget UI is **explicit only**; missing override = **0.00** (abandon implicit `annual ÷ 12` in monthly view and monthly sums).
3. **Forecast model unchanged in spirit** — yearly forecast still uses annual budget minus elapsed monthly plans, where elapsed plans resolve via **override OR `annual ÷ 12`**. Actual base for forecast **excludes the current calendar month** (closed months only).

Annual and monthly display amounts remain **independent** — no validation that `12 × monthly = annual`.

## Problem

Today the yearly view edits only the annual estimate. Users who plan with a fixed monthly amount must edit month-by-month. Additionally, implicit `annual ÷ 12` in the monthly view conflates annual and monthly planning; users want annual as a separate yearly target and explicit monthly rows (or zero).

## Decisions log

| Topic | Decision |
|-------|----------|
| UI layout | Two inputs side-by-side; same save/cancel flow |
| Monthly bulk scope | Current month through December (current year); all 12 months (future year); none (past year) |
| Past months on bulk | **Never modified** |
| Future months with existing override | **Keep override** (skip on bulk write) |
| Empty monthly on save | **No-op** for monthly rows — only annual persisted |
| Annual vs monthly (display) | **Independent** — no sum validation |
| Monthly display without override | **0.00** — no implicit `annual ÷ 12` |
| Forecast monthly resolution | Override **or** `annual ÷ 12` when annual exists |
| Forecast actual base | YTD through **end of previous month** (exclude current month) for current year; full year for past years; 0 for future years |
| Execution / progress (yearly view) | **Unchanged** — full YTD actual vs annual plan (includes current month) |
| Save approach | Single atomic endpoint `SaveYearlyCategoryPlan` |
| Resting display (yearly plan column) | Annual plan only |
| Monthly pre-fill in edit | `monthly_template` — uniform explicit amount on eligible months without override, else `null` |
| Component | New `YearlyPlanEditCell.vue` |
| Yearly plan column width | `--budget-col-plan: 18rem` |
| Telemetry | `category_estimate_yearly_plan_saved` |

## Plan amount resolution (core model)

Two resolution paths in `CategoryPlanAmount`:

### Display — `monthly()` (monthly view, monthly sums, allocation hint)

```
if monthly override row with non-null amount → use it
else → '0.00'
```

Annual estimate is **not** consulted for display.

### Forecast — `monthlyForForecast()` (`BudgetForecast::elapsedPlansSum` only)

```
if monthly override row with non-null amount → use it
else if annual amount set → annual ÷ 12 (bcdiv, scale 2)
else → skip (contributes 0 to sum)
```

### Annual — `annual()` (unchanged)

Returns annual estimate amount or null.

## Business rules — bulk monthly write

Given `year` (view year) and eligible months (same calendar rules as before):

| View year | Eligible months for bulk write |
|-----------|--------------------------------|
| Past year | None |
| Current year | Current calendar month .. 12 |
| Future year | 1 .. 12 |

For each eligible month `m`, write `monthly_amount` **only if** there is no `CategoryMonthlyEstimate` row for `(category_id, year, m)` **or** the row exists with `amount = null`.

Past months are never written.

### Save payload semantics

| Field | Value | Effect |
|-------|-------|--------|
| `annual_amount` | Valid or null | Upsert `CategoryAnnualEstimate` |
| `monthly_amount` | Valid positive amount | Bulk write to eligible months without override |
| `monthly_amount` | Empty / omitted | Skip bulk monthly entirely |

### Monthly template (edit pre-fill)

Among eligible months **without override**, if all explicit `CategoryMonthlyEstimate` rows (non-null amount) share the same value, return that value; otherwise `null`. Derived values are **not** considered (no `annual ÷ 12` in template logic).

## Yearly forecast (per category)

Reference month for forecast: `M = BudgetForecast::referenceMonth(year)` (current month for current year, 12 for past, 0 for future).

```
closedMonth = max(0, M - 1)   // for current year; use 12 for past year; 0 for future

actual_base = sum of actual transactions for months 1..closedMonth
              (same category-type primary amount, exclude transfers)

elapsed_plans = sum of monthlyForForecast() for months 1..closedMonth

remainder = max(0, annual_plan - elapsed_plans)   // when annual_plan set

forecast = annual_plan is null
         ? actual_base
         : actual_base + remainder
```

When `annual_plan − elapsed_plans` is negative, remainder = 0 (forecast does not drop below `actual_base`).

**January (current year):** `closedMonth = 0` → `actual_base = 0`, `elapsed = 0`, `forecast = annual_plan`.

**Execution column** on yearly view continues to use full YTD through month `M` (includes partial current month). **Progress** compares `annual_plan` to that full YTD actual.

## UI design

### Resting state

Formatted annual plan + pencil (unchanged).

### Edit state

```
[ annual input w-28 ] [ monthly input w-28 ] [Check] [X]
```

- Placeholders: `budget.yearly.planPlaceholder`, `budget.yearly.monthlyPlanPlaceholder`
- `Enter` → save both; `Escape` → cancel
- Decimal validation per field (same regex as today)
- One category row in edit mode at a time
- No align-to-actual on yearly view

### Column width (yearly page scoped CSS)

```css
--budget-col-plan: 18rem;
```

## Architecture

### Backend

| Piece | Location |
|-------|----------|
| Action | `App\Actions\Categories\SaveYearlyCategoryPlan` |
| Request | `App\Http\Requests\Categories\SaveYearlyCategoryPlanRequest` |
| Support | `App\Support\Budgets\YearlyMonthlyTemplate` |
| Route | `PATCH categories/{category}/estimates/yearly-plan` → `categories.estimates.yearly-plan` |
| Controller | `CategoryController::saveYearlyCategoryPlan` |

### `SaveYearlyCategoryPlan::handle`

Inside `DB::transaction`:

1. Upsert `CategoryAnnualEstimate` for `(category_id, year)`.
2. If `monthly_amount` is non-null: bulk write eligible months without override.
3. Telemetry `category_estimate_yearly_plan_saved`.

### `ListYearlyBudget` changes

- Add `monthly_template` per row via `YearlyMonthlyTemplate`.
- Forecast: use `actual_base` (closed months) + updated `elapsedPlansSum` via `monthlyForForecast`.
- Add query/helper for actual through `closedMonth` (or subtract current month from existing YTD query).

### `ListMonthlyBudget` changes

- `CategoryPlanAmount::monthly()` returns `0.00` when no override (update allocation hint accordingly).
- Soft allocation hint still compares sum of explicit monthly plans to annual sum (informational).

### Frontend

| File | Change |
|------|--------|
| `YearlyPlanEditCell.vue` | New dual-input cell |
| `BudgetCategorySection.vue` | Use `YearlyPlanEditCell` when `variant === 'yearly'` |
| `Yearly.vue` | `saveYearlyPlan(row, annualRaw, monthlyRaw)`; extend `BudgetRow` with `monthly_template` |

```
Save → router.patch(categories.estimates.yearly-plan, {
  year,
  annual_amount,
  monthly_amount, // omit when empty
})
```

Remove yearly usage of `categories.estimates.annual` single-field flow.

## i18n

| Key | PL | EN |
|-----|----|----|
| `budget.yearly.monthlyPlanPlaceholder` | Opcjonalnie | Optional |
| `budget.yearly.monthlyPlanLabel` | Plan miesięczny | Monthly plan |

## Edge cases

| Case | Rule |
|------|------|
| Past year bulk monthly | No monthly writes; annual still saves |
| Future year bulk | Months 1–12 without override |
| Monthly only (annual empty) | Annual cleared; eligible months get monthly amount |
| All eligible months overridden | Bulk monthly no-op for months; annual still saves |
| Mixed `monthly_template` | Edit input starts empty |
| Explicit monthly = 0 | Valid override; display 0; forecast uses 0 for that month |
| No annual, no monthly override | Display 0; forecast = actual_base only |

## Testing

| Area | Assertion |
|------|-----------|
| `CategoryPlanAmount::monthly` | No override → `0.00` even when annual set |
| `CategoryPlanAmount::monthlyForForecast` | No override + annual → `annual ÷ 12` |
| Bulk save (June current year) | Months 6–12 without override updated; 1–5 untouched |
| Override protection | September = 2000 stays when bulk = 400 |
| Empty `monthly_amount` | Existing monthly rows unchanged |
| Forecast June | Uses actual Jan–May + remainder; elapsed uses forecast resolution |
| Forecast January | `forecast = annual_plan` when no closed months |
| Yearly execution column | Still full YTD including current month |
| Authorization | Same `update` policy as estimate endpoints |

Run: `./vendor/bin/sail artisan test --compact --filter=Categories` and `--filter=YearlyBudget` and `--filter=BudgetForecast`.

## PRD alignment (follow-up)

Update `.docs/prd.md` §1 and FR-C4: monthly display default is **0**, not `roczny ÷ 12`; forecast section documents dual resolution.

## Out of scope

- Monthly view dual-field edit UX change (single field remains)
- Align-to-actual on yearly view
- Soft hint on yearly edit form (sum vs annual)
- Bulk clear of monthly overrides from yearly view
- Pocket section changes
- DB schema changes

## Self-review (2026-06-11)

- [x] No TBD placeholders
- [x] Display vs forecast split is explicit and non-contradictory
- [x] Bulk / override / empty-monthly rules match brainstorming
- [x] Forecast actual exclusion applies only to forecast, not execution/progress
- [x] Single implementation plan scope
- [x] Atomic save via one action + transaction

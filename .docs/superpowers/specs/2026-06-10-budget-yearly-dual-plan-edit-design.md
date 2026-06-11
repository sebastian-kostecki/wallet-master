# Budget yearly view — dual plan edit (annual + optional monthly) — design spec

**Status:** Superseded by `.docs/superpowers/specs/2026-06-11-budget-yearly-dual-plan-edit-design.md` (display/forecast model revised)  
**Builds on:** `.docs/superpowers/specs/2026-06-03-categories-budget-estimates-design.md`, `.docs/superpowers/specs/2026-06-10-budget-plan-cell-actions-design.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `resources/js/pages/budget/Yearly.vue`, new `YearlyPlanEditCell.vue`, `BudgetCategorySection.vue`, `ListYearlyBudget`, new `SaveYearlyCategoryPlan` action, routes, requests, tests, i18n

## Summary

Extend yearly budget category plan editing so a single inline edit row exposes **two side-by-side inputs**:

1. **Annual plan** — saves `CategoryAnnualEstimate` (primary yearly target; nullable as today).
2. **Optional monthly plan** — when filled on save, writes `CategoryMonthlyEstimate` rows for **current and future months** in the selected year, but **only** for months that do **not** already have their own override.

Annual and monthly amounts are **independent** — no validation that `12 × monthly = annual`.

Clearing the monthly input on save is a **no-op** for existing monthly overrides (only the annual amount is persisted).

## Problem

Today the yearly view edits only the annual estimate. Users who plan with a fixed monthly amount must open the monthly view twelve times (or month-by-month). They want to set both targets from the yearly screen in one action, while respecting past locked months and individually edited future months.

## Decisions log

| Topic | Decision |
|-------|----------|
| UI layout | **A** — two inputs side-by-side in the table row; same save/cancel flow |
| Monthly apply scope | Current month through December of selected year |
| Past months | **Never modified** |
| Future months with existing override | **Keep override** (partial B) |
| Current+future without override | Receive the new monthly amount |
| Empty monthly on save | **No-op** — do not touch any existing monthly overrides |
| Annual vs monthly relationship | **Independent** — no sum validation |
| Approach | **A** — single atomic endpoint `SaveYearlyCategoryPlan` |
| Display (resting) | Annual plan only (unchanged) |
| Monthly pre-fill in edit | Backend `monthly_template` — uniform eligible amount or `null` |
| Component | New `YearlyPlanEditCell.vue` (keep `EditableEstimateCell` for monthly view) |
| Yearly plan column width | `--budget-col-plan: 18rem` |
| Telemetry | New event `category_estimate_yearly_plan_saved` |

## Business rules

### Eligible months for bulk monthly write

Given `year` (view year) and `referenceMonth` (same rule as `BudgetForecast::referenceMonth`):

| View year | `referenceMonth` | Months eligible for bulk write |
|-----------|------------------|--------------------------------|
| Past year | 13 (none) | None |
| Current year | Current calendar month (1–12) | `referenceMonth` .. 12 |
| Future year | 1 | 1 .. 12 |

For each eligible month `m`, write `monthly_amount` **only if** there is no `CategoryMonthlyEstimate` row for `(category_id, year, m)` **or** the row exists with `amount = null`.

**Past months are never written**, regardless of override state.

### Save payload semantics

| Field | Present / empty | Effect |
|-------|-----------------|--------|
| `annual_amount` | Any valid value or null | Upsert `CategoryAnnualEstimate` |
| `monthly_amount` | Valid positive amount | Bulk write to eligible months without override (rules above) |
| `monthly_amount` | Empty / omitted | Skip bulk monthly entirely |

### Monthly template (edit pre-fill)

`monthly_template` for a category row = the single amount shared by **all eligible months without override** that currently resolve to an explicit monthly estimate row with non-null amount; if eligible months have different explicit amounts or mix explicit + derived (`annual ÷ 12`), return `null` (empty input).

Eligible months for template derivation use the same `referenceMonth` rule as bulk write.

## UI design

### Resting state

Unchanged: formatted annual plan + pencil icon.

### Edit state

```
[ annual input w-28 ] [ monthly input w-28 ] [Check] [X]
```

- Labels via placeholders: annual uses existing `budget.yearly.planPlaceholder`; monthly uses new `budget.yearly.monthlyPlanPlaceholder` (“Opcjonalnie” / “Optional”).
- Keyboard: `Enter` → save both; `Escape` → cancel (same as today).
- Validation: same decimal regex per field (`/^\d+([.,]\d{1,2})?$/` or empty).
- Only one category row in edit mode at a time (existing behaviour).
- No align-to-actual button on yearly view (unchanged).

### Column width

Yearly page scoped CSS:

```css
--budget-col-plan: 18rem; /* was 9rem */
```

## Architecture

### New backend pieces

| Piece | Location |
|-------|----------|
| Action | `App\Actions\Categories\SaveYearlyCategoryPlan` |
| Request | `App\Http\Requests\Categories\SaveYearlyCategoryPlanRequest` |
| Support | `App\Support\Budgets\YearlyMonthlyTemplate` (derive template + eligible months) |
| Route | `PATCH categories/{category}/estimates/yearly-plan` → `categories.estimates.yearly-plan` |
| Controller | `CategoryController::saveYearlyCategoryPlan` |

### `SaveYearlyCategoryPlan::handle`

Inside `DB::transaction`:

1. Upsert `CategoryAnnualEstimate` for `(category_id, year)` with `annual_amount`.
2. If `monthly_amount` is non-null and non-empty:
   - Resolve eligible months via `YearlyMonthlyTemplate::eligibleMonths($year)`.
   - For each month, skip if `CategoryMonthlyEstimate` exists with non-null `amount`.
   - Otherwise `updateOrCreate` with `amount = monthly_amount`.
3. Record telemetry `category_estimate_yearly_plan_saved`.

Reuse validation rules from existing estimate requests (`year`, non-negative nullable decimal amounts).

### `ListYearlyBudget` row shape

Add to each row:

```php
'monthly_template' => YearlyMonthlyTemplate::forCategory(...),
```

Existing keys unchanged (`annual_plan`, `forecast`, etc.).

### Frontend data flow

```
Pencil click → edit row with annual_plan + monthly_template
Save → router.patch(categories.estimates.yearly-plan, {
  year,
  annual_amount: trimmed annual or null,
  monthly_amount: trimmed monthly or null (omit key if empty),
})
→ Inertia reload → editing state cleared
```

Remove yearly usage of `saveAnnualEstimate` / single-field `EditableEstimateCell`; monthly view keeps existing flow.

## Component changes

### `YearlyPlanEditCell.vue` (new)

Props: `annualPlan`, `monthlyTemplate`, `currency`, `inputIdPrefix`, placeholders, labels, `isEditing`.  
Emits: `start-edit`, `cancel`, `save(annualRaw, monthlyRaw)`.

Display: single formatted annual amount + pencil (reuse styling from `EditableEstimateCell`).  
Edit: dual inputs + save/cancel buttons.

### `BudgetCategorySection.vue`

When `variant === 'yearly'`, render `YearlyPlanEditCell` instead of `EditableEstimateCell`.  
New props/emits for dual save forwarded to `Yearly.vue`.

### `Yearly.vue`

Replace `saveAnnualEstimate` with `saveYearlyPlan(row, annualRaw, monthlyRaw)`.  
Extend `BudgetRow` type with `monthly_template?: string | null`.

## i18n

| Key | PL | EN |
|-----|----|----|
| `budget.yearly.monthlyPlanPlaceholder` | Opcjonalnie | Optional |
| `budget.yearly.monthlyPlanLabel` | Plan miesięczny | Monthly plan |

Use placeholder for inline edit; label available for aria if needed.

## Edge cases

| Case | Rule |
|------|------|
| Viewing past year | Monthly bulk write affects no months; annual still saves; edit monthly input allowed but bulk scope empty |
| Viewing future year | All 12 months eligible (without override) |
| User sets monthly only (annual empty) | Annual cleared/null; eligible months get monthly amount |
| All future months already overridden | Bulk monthly is no-op for months; annual still saves |
| Mixed monthly_template | Input starts empty; user may enter new uniform amount |
| Sum of months ≠ annual | Allowed — no UI warning in v1 (consistent with FR-C4 soft hint scope; hint not required here) |

## Testing

| Test | Assertion |
|------|-----------|
| Save annual + monthly (current year, June) | Months 6–12 without override get amount; months 1–5 untouched |
| Past month with override | Unchanged after bulk save |
| Future month with override (September = 2000) | Stays 2000 when bulk monthly = 400 |
| Empty monthly_amount | Existing monthly rows unchanged |
| `monthly_template` uniform | Returns shared amount |
| `monthly_template` mixed | Returns null |
| Past year bulk | No monthly rows created/updated |
| Authorization | Same `update` policy as existing estimate endpoints |

Run: `./vendor/bin/sail artisan test --compact --filter=Categories` and `--filter=YearlyBudget`.

## Out of scope

- Changing monthly view edit UX
- Align-to-actual on yearly view
- Soft hint “sum of months vs annual” on yearly edit form
- New DB table/column for monthly template
- Bulk clear/remove of monthly overrides from yearly view
- Pocket section changes

## Self-review (2026-06-10)

- [x] No TBD placeholders
- [x] Consistent with FR-C3/C4 estimate model (monthly rows, not new template entity)
- [x] Past / override / empty-monthly rules match brainstorming decisions
- [x] Single implementation plan scope
- [x] Atomic save via one action + transaction

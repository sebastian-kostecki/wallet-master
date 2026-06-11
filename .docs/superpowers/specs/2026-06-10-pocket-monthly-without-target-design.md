# Pocket monthly contribution without target — design spec

**Status:** Approved in brainstorming (2026-06-10)  
**Builds on:** pockets target model (`.docs/superpowers/specs/2026-06-04-goals-target-model-design.md`), budget pocket section (`.docs/superpowers/specs/2026-06-06-budget-pocket-section-alignment-design.md`)  
**Canonical requirements target:** `.docs/prd.md` (extends FR-P2, budget pocket rows)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)

## Summary

Allow users to set an optional **monthly savings amount** (`monthly_contribution`) on a pocket **without** defining a savings target (`target_amount`). The amount appears in the **monthly budget** pocket section (`pocket_rows[].monthly_plan`) and rolls into expense plan totals via `BudgetSummary::withPockets`. Configuration happens in **Create/Edit** forms only; the pockets index list stays unchanged.

## Problem

Today, `monthly_contribution` is only available when `target_amount` is set. Form requests clear planning fields (including `monthly_contribution`) when the target is empty, and `PocketPlanningProjection::monthlyPlanForBudget()` returns a plan only when `planning_mode` is set (which requires a target). Users who track savings in a pocket without a fixed goal cannot budget a recurring monthly allocation.

## Decisions log

| Topic | Decision |
|-------|----------|
| Where to configure | Create/Edit forms only (`Create.vue`, `Edit.vue`) — **not** inline on `Index.vue` |
| Index display | **No change** — monthly amount not shown on pocket list |
| Required when no target | **Optional** — pocket may exist with neither target nor monthly plan |
| Data model | **No migration** — reuse existing `monthly_contribution` column |
| `planning_mode` without target | Stays `null`; monthly amount is a standalone budget plan field |
| Approach | **A — standalone field** (not auto-setting `planning_mode = monthly`, no new enum) |
| Budget integration | Extend `monthlyPlanForBudget()` default branch to return `monthly_contribution` |
| Projections | `recommended_monthly`, `projected_completion_date`, progress bar — unchanged (target-only) |
| Archived pockets | Hidden from budget (unchanged) |
| Cross-currency pockets | Row visible; not merged into summary P&L (unchanged) |

## UX — Create / Edit forms

### Field layout

- Move **“Monthly contribution”** (`monthly_contribution`) **outside** the `v-if="hasTarget"` planning block.
- Field is always visible and optional.
- Planning block (segmented control `monthly` / `by_date` + `target_date`) remains **inside** `hasTarget` only.

### Submit behaviour (frontend)

| Condition | Cleared on submit |
|-----------|-------------------|
| No target (`hasTarget === false`) | `target_amount`, `planning_mode`, `target_date` |
| No target | **Keep** `monthly_contribution` |
| Target + `planning_mode === 'monthly'` | `target_date` |
| Target + `planning_mode === 'by_date'` | `monthly_contribution` |

### Form default

When editing/creating without a target, do not send `planning_mode: 'monthly'` by default — leave it empty/null on submit when there is no target.

## Validation

### StorePocketRequest / UpdatePocketRequest

**`prepareForValidation` (updated):**

When `target_amount` is empty/null, merge:

```php
'target_amount' => null,
'planning_mode' => null,
'target_date' => null,
// monthly_contribution is NOT cleared
```

**Rules (unchanged intent, clarified scope):**

| Field | Rule |
|-------|------|
| `target_amount` | `nullable`, `numeric`, `min:0` |
| `planning_mode` | `nullable`, enum; `required_with:target_amount` (Store) / `requiredIf` when target present (Update) |
| `monthly_contribution` | `nullable`, `numeric`, `min:0`; allowed without target; `required_if:planning_mode,monthly`; `prohibited_if:planning_mode,by_date` |
| `target_date` | unchanged |

## Budget logic

### `PocketPlanningProjection::monthlyPlanForBudget()` (updated)

```php
return match ($pocket->planning_mode) {
    PocketPlanningMode::Monthly => $pocket->monthly_contribution !== null
        ? (string) $pocket->monthly_contribution
        : null,
    PocketPlanningMode::ByDate => self::recommendedMonthly($pocket, $balance),
    default => $pocket->monthly_contribution !== null
        ? (string) $pocket->monthly_contribution
        : null,
};
```

### Downstream (unchanged)

- `ListMonthlyBudget::buildPocketRows()` — uses `monthlyPlanForBudget()` as today.
- `BudgetSummary::withPockets()` — adds non-null `monthly_plan` to `plan.expense` when currency matches summary currency.
- `BudgetPocketSection.vue` — displays `row.monthly_plan` as today.

## Edge cases

| Scenario | Behaviour |
|----------|-----------|
| No target, no monthly amount | `monthly_plan = null`; no plan in budget summary |
| No target, monthly = 0 | Save OK; treat as no plan (`null` or `0.00` — consistent with existing budget null-skip behaviour) |
| Remove target, keep monthly | Monthly amount persists; `planning_mode` → `null`; budget still shows plan |
| Add target to pocket with monthly | Amount preserved; user picks mode; `by_date` submit clears monthly (existing) |
| Target + monthly mode | Unchanged — monthly required |
| Target + by_date mode | Unchanged — monthly prohibited |

## Out of scope

- Monthly amount on `Index.vue` (read-only or editable)
- Inline quick-edit on pocket list
- Yearly budget pocket section
- New `planning_mode` enum value

## Testing

### Feature — `tests/Feature/Pockets/`

- Create pocket without target with `monthly_contribution = 200` → 201/redirect OK; `planning_mode` null; amount stored.
- Create pocket without target and without monthly → OK; `monthly_contribution` null.
- Update: clear target while keeping monthly → amount persists after save.
- Regression: target + `monthly` mode still requires contribution; target + `by_date` still rejects `monthly_contribution`.

### Feature — `tests/Feature/Budgets/MonthlyBudgetTest.php`

- Pocket without target, `monthly_contribution = 500` → `pocket_rows[].monthly_plan === '500.00'`.
- Amount included in `summary.plan.expense` via `withPockets`.

### Unit — `tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php`

- `monthlyPlanForBudget`: returns contribution when `planning_mode` null and amount set.
- Returns `null` when no target and no contribution.

### Verification

- `vendor/bin/pint --dirty --format agent` on changed PHP files
- `./vendor/bin/sail artisan test --compact --filter=Pocket` and budget pocket tests

## Files to touch (implementation hint)

| Layer | Files |
|-------|-------|
| Requests | `StorePocketRequest.php`, `UpdatePocketRequest.php` |
| Support | `PocketPlanningProjection.php` |
| Frontend | `Create.vue`, `Edit.vue` |
| Tests | `PocketPlanningTest.php` or `PocketCrudTest.php`, `PocketPlanningProjectionTest.php`, `MonthlyBudgetTest.php` |

# Budget monthly summary — include pockets — design spec

**Status:** Approved in brainstorming (2026-06-06)  
**Builds on:** `.docs/prd.md` (FR-C5, FR-P5), `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `app/Support/Budgets/BudgetSummary.php`, `app/Actions/Budgets/ListMonthlyBudget.php`, tests in `tests/Unit/Support/Budgets/` and `tests/Feature/Budgets/MonthlyBudgetTest.php`

## Summary

Include active savings pockets (kieszenie) in the **monthly budget summary card** (`BudgetSummaryCard`). Pocket monthly plans contribute to **planned expenses**; pocket execution contributes **saved → expenses** and **released → income**. The pockets table at the bottom of the page stays unchanged — only the aggregated summary values change.

## Problem

Today `ListMonthlyBudget` builds `summary` exclusively from P&L category rows via `BudgetSummary::fromRows()`. Pocket rows (`pocket_rows`) are shown in a separate section but do not affect the top summary card. Users who save or withdraw from pockets cannot see those flows reflected in plan vs execution totals.

## Decisions log

| Topic | Decision |
|-------|----------|
| Scope | **Monthly budget only** — yearly view has no pocket section; unchanged |
| Plan → income | No pocket contribution |
| Plan → expense | **+ sum of `monthly_plan`** across active, non-archived pockets in summary currency (null plan skipped) |
| Execution → expense | **+ sum of `saved`** (ROR→Savings transfer legs in month) |
| Execution → income | **+ sum of `released`** (Savings→ROR transfer legs in month) |
| Balance | Recalculated: `income − expense` after merge |
| Expense progress % | Recalculated on **merged** plan expense vs merged execution expense |
| Income progress % | **P&L categories only** — `released` affects execution income and balance but not the income progress bar (no pocket income plan exists) |
| Currency filter | Only pockets whose `currency.code` matches summary currency (MVP: PLN via `BudgetCurrency::pln()`) |
| UI layout | **No Vue changes** — same `BudgetSummaryCard`; backend sends updated `summary` prop |
| Architecture | Pure merge helper in `Support/Budgets/`; `ListMonthlyBudget` calls it after `fromRows()` and after `buildPocketRows()` |
| Approach | **A — explicit merge** (`BudgetSummary::withPockets()`), not pseudo-category rows |

## Semantics

### Aggregation rules

Given existing P&L summary `$summary` and pocket rows `$pocketRows` (same shape as Inertia `pocket_rows`):

```
plan.expense      += Σ monthly_plan (non-null, matching currency)
execution.expense += Σ saved
execution.income  += Σ released
plan.balance      = plan.income − plan.expense
execution.balance = execution.income − execution.expense
progress.expense_percent = BudgetProgress::percent(plan.expense, execution.expense)
progress.income_percent  = unchanged from P&L-only pass (before pocket merge)
```

### Example

P&L: plan income 5000, plan expense 3000, execution income 4800, execution expense 3200.

One pocket: plan 200, saved 200, released 150.

| | Income | Expense | Balance |
|---|--------|---------|---------|
| Plan | 5000 | 3200 | 1800 |
| Execution | 4950 | 3400 | 1550 |

Net pocket effect on balance: `+150 − 200 = −50`.

### Relationship to pocket table

The per-pocket table columns (Plan / Odłożono / Wypłacono / Saldo) remain the source of truth at row level. Summary is an **aggregate overlay** — sums must match totals implied by pocket rows plus P&L rows.

## Backend design

### `BudgetSummary::withPockets()`

New static method in `app/Support/Budgets/BudgetSummary.php`:

```php
/**
 * @param  array{plan: ..., execution: ..., progress: ...}  $summary
 * @param  list<array{monthly_plan: ?string, saved: string, released: string, currency: array{code: string}}>  $pocketRows
 * @param  string  $summaryCurrencyCode  e.g. 'PLN'
 */
public static function withPockets(array $summary, array $pocketRows, string $summaryCurrencyCode): array
```

Implementation notes:

- Filter `$pocketRows` where `currency.code === $summaryCurrencyCode`.
- Use `bcadd` / `bcsub` with scale 2 (consistent with existing `BudgetSummary`).
- Preserve `income_percent` from input `$summary` (P&L-only).
- Recompute `expense_percent` after merge.

### `ListMonthlyBudget`

Current order:

1. Build category `$this->rows`
2. `$this->summary = BudgetSummary::fromRows(...)`
3. `$this->pocketRows = buildPocketRows(...)`

New order:

1. Build category `$this->rows`
2. `$this->pocketRows = buildPocketRows(...)` — move before summary **or** merge after both exist
3. `$summary = BudgetSummary::fromRows(...)`
4. `$this->summary = BudgetSummary::withPockets($summary, $this->pocketRows, BudgetCurrency::pln()['code'])`

Either order works; pocket rows must be available before final summary assignment.

No controller, Resource, route, migration, or frontend changes required.

## Frontend

No changes. `Monthly.vue` already passes `summary` to `BudgetSummaryCard`. Values update automatically when backend props change.

Optional follow-up (out of scope): tooltip or footnote explaining that summary includes pockets — not required for v1.

## Testing

### Unit — `tests/Unit/Support/Budgets/BudgetSummaryTest.php`

1. **withPockets adds plan to expense and saved/released to execution** — given P&L summary + one pocket row; assert merged totals and balance.
2. **withPockets skips null monthly_plan** — plan expense unchanged; saved/released still applied.
3. **withPockets skips non-matching currency** — EUR pocket ignored when summary currency PLN.
4. **withPockets recalculates expense_percent only** — income_percent unchanged from input.

### Feature — `tests/Feature/Budgets/MonthlyBudgetTest.php`

1. **summary includes pocket saved as expense and released as income** — user with P&L estimates + pocket with transfers in month; assert Inertia `summary.execution.expense` and `summary.execution.income`.
2. **summary plan expense includes pocket monthly_plan** — pocket with `monthly_contribution`; assert `summary.plan.expense`.

Extend existing test `monthly budget exposes summary currency and progress without difference` only if pocket fixtures would clarify; prefer dedicated tests.

## PRD alignment

Extends FR-C5 monthly view: summary card now reflects savings envelope activity without moving pockets into P&L categories. Consistent with FR-P5 (pockets as separate section) and decision to exclude internal transfers from category P&L.

Consider updating FR-C5 acceptance criteria in `.docs/prd.md` in a follow-up PR if product wants the requirement canonical — not blocking implementation.

## Out of scope

- Yearly budget summary + pockets
- Separate summary row labeled “Kieszenie” in the card
- Income progress bar including `released`
- Multi-currency summary aggregation beyond filtering to summary currency
- Changes to `allocation_hint` (remains P&L-only)

## Verification

```bash
./vendor/bin/sail artisan test --compact --filter=BudgetSummary
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
vendor/bin/pint --dirty --format agent
```

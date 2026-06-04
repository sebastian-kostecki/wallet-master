# Goals target model refactor — design spec

**Status:** Approved in brainstorming (2026-06-04)  
**Supersedes (partial):** goal estimate model in `.docs/superpowers/specs/2026-06-03-budget-goals-ux-design.md` (FR-G2, GoalAnnualEstimate / GoalMonthlyEstimate, year selector on `/goals`)  
**Builds on:** goals + budget UX (shipped), transfer category decoupling (shipped or in progress)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Canonical requirements target:** `.docs/prd.md`

## Summary

Replace the **year-scoped estimate model** for savings goals (annual + monthly overrides per calendar year) with a **timeless goal envelope**: optional target amount, optional planning mode (fixed monthly contribution **or** target date), icon and color, cumulative progress bar, and manual archiving. Goals remain separate from P&L categories and are still tracked via `goal_id` on transfers involving `Savings` accounts (FR-G3 unchanged).

## Problem

The shipped goals UX mirrors categories: a year selector on `/goals`, annual estimates, and monthly overrides edited on the monthly budget. That model fits **recurring budget plans** (P&L), not **savings envelopes** (“save 5 000 PLN for vacation”). Users think in terms of a **target sum**, optional **deadline or monthly pledge**, and **overall progress** — not “how much did I plan for goal X in calendar year 2026”.

## Decisions log

| Topic | Decision |
|-------|----------|
| Estimate tables | **Remove** `goal_annual_estimates` and `goal_monthly_estimates`; full replacement (no coexistence) |
| Goal timelessness | Goal definition has **no year**; like categories catalog, not like budget P&L rows |
| Target amount | Optional `target_amount`; `null` = open-ended jar (collect without limit) |
| Open-ended UI | Show **cumulative balance only**; **no** progress bar |
| Planning input | **Mutually exclusive:** user sets **monthly contribution** OR **target date**, not both as editable inputs |
| Mode `monthly` | User’s `monthly_contribution` is a **fixed declaration**; only **projected completion date** adjusts based on actual transfer history |
| Mode `by_date` | User’s `target_date` is fixed; system computes **recommended monthly** from remaining amount and remaining time, using **current cumulative balance** |
| Completed state | **Computed** badge when `balance >= target_amount`; goal **remains** (can keep saving); no auto-archive |
| Archive | **Manual** `is_archived`; hidden from default lists and monthly budget section |
| Visual identity | **Icon + color** (same Lucide whitelist and hex palette as categories) |
| Goals CRUD UI | **Parity with categories** — list with reorder; separate create/edit pages; no year selector |
| Progress bar | On goals list/detail when `target_amount` set: `balance / target_amount` (may exceed 100%) |
| Monthly budget section | Keep per-month saved / released / balance; **Plan** column from goal fields (see metrics); optional cumulative progress hint |
| Architecture | Variant A domain **`Goals`**; calculations in `Support/Goals/`; no new `Services/` layer |
| Yearly budget | **No** goals section in v1 (unchanged from prior optional scope) |

## Data model

### Goal (updated)

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | FK | Owner |
| `name` | string | Display name |
| `icon` | string | Lucide icon name (kebab-case), category whitelist |
| `color` | string | Hex from fixed palette |
| `sort_order` | int | Display order |
| `target_amount` | decimal(12,2), nullable | Target sum; `null` = open-ended |
| `planning_mode` | enum, nullable | `monthly` \| `by_date`; required when `target_amount` is set; `null` when open-ended |
| `monthly_contribution` | decimal(12,2), nullable | User declaration; **writable only** when `planning_mode = monthly` |
| `target_date` | date, nullable | User deadline; **writable only** when `planning_mode = by_date` |
| `is_archived` | boolean, default false | Manual hide from default UI |

**Validation rules:**

- `target_amount` ≥ 0 when set.
- When `target_amount` is null: `planning_mode`, `monthly_contribution`, and `target_date` must all be null.
- When `target_amount` is set: `planning_mode` required.
- When `planning_mode = monthly`: `monthly_contribution` required, `target_date` null.
- When `planning_mode = by_date`: `target_date` required (≥ today on create; existing goals may keep past dates — show overdue state in UI), `monthly_contribution` null in DB (computed at read time only).
- Delete blocked when goal has linked transactions (unchanged FR-G1).

### Removed tables

- `goal_annual_estimates`
- `goal_monthly_estimates`

Models `GoalAnnualEstimate`, `GoalMonthlyEstimate` and actions `SaveAnnualEstimate`, `SaveMonthlyEstimate` removed. Routes `goals.estimates.*` removed.

### Transaction extension

Unchanged: `goal_id` on transactions; FR-G3 (required on Savings transfers), FR-G4 (optional on P&L).

## Metrics

### Cumulative balance (lifetime)

For goal *G*, all time, using savings-account transfer legs (`transfer_id` set, account `type = Savings`):

| Metric | Definition |
|--------|------------|
| `saved_total` | Sum of legs where `goal_id = G` and `amount > 0` |
| `released_total` | Sum of `ABS(amount)` where `goal_id = G` and `amount < 0` |
| `balance` | `saved_total − released_total` |

Implementation: extend `Support/Goals/GoalTransactionMetrics` (or sibling class) with `cumulative(User, Goal)`; reuse `BudgetTransactionQuery` without period filter.

### Computed UI fields (not persisted)

| Field | When |
|-------|------|
| `is_completed` | `target_amount` set and `balance >= target_amount` |
| `progress_percent` | `target_amount` set: `min(100, round(balance / target_amount * 100))` for bar fill; display may show >100% text |
| `recommended_monthly` | `planning_mode = by_date`: see formula below |
| `projected_completion_date` | `planning_mode = monthly`: see formula below |

### Mode `by_date` — recommended monthly

```
remaining      = max(0, target_amount − balance)
months_left    = max(1, count of full calendar months from start of current month through target_date inclusive)
recommended_monthly = remaining / months_left   (scale 2, bcdiv)
```

Recalculated on every read after transfers change balance or time passes. Shown read-only in goal form and monthly budget **Plan** column.

If `target_date` is in the past and `balance < target_amount`: UI shows overdue state (e.g. badge „Po terminie”) and recommended_monthly uses `months_left = 1` (entire remaining amount due now).

### Mode `monthly` — projected completion date

User declaration `monthly_contribution` stays fixed.

**Effective savings rate** (for projection only):

1. Group net savings per calendar month from `goal.created_at` through today: for each month, `net = saved − released` on Savings legs for this goal.
2. Consider only months where `net > 0`.
3. If at least one such month: `effective_rate = sum(net) / count(months with net > 0)`.
4. Else: `effective_rate = monthly_contribution` (cold start).

```
remaining = max(0, target_amount − balance)
months_needed = ceil(remaining / effective_rate)   (minimum 1 if remaining > 0)
projected_completion_date = end of month reached by adding months_needed to current month
```

If `remaining = 0`: projected date = today (or null — implementation may omit).

### Monthly budget row (FR-G5 / FR-C5 section)

Per goal *G* in month *M* (unchanged monthly legs):

| Column | Source |
|--------|--------|
| **Plan** | `monthly_contribution` if mode `monthly`; else `recommended_monthly` if mode `by_date`; else „—” |
| **Saved / Released / Balance** | Existing `GoalTransactionMetrics::forMonth()` |
| **Progress hint** (optional v1) | Text: `{balance} / {target_amount}` and percent when target set |

Archived goals excluded from default monthly budget section.

## UI / UX

### Cele — index (`/goals`)

- Match categories IA: sections/list with drag reorder (`sort_order`), icon + color badge, create button → `/goals/create`, edit → `/goals/{id}/edit`.
- Row/card per goal:
  - Name, icon, color
  - Cumulative balance (always)
  - Progress bar when `target_amount` set
  - Badge **Ukończony** when `is_completed`
  - Secondary line: mode-specific info (`200 PLN/mies.` or `Do 15.08.2026 → 420 PLN/mies.` or projected date)
  - Actions: edit, archive/unarchive, delete (if no transactions)
- Filter tabs: **Aktywne** (default, non-archived) / **Zarchiwizowane** / **Wszystkie**
- **Remove:** year selector, annual estimate inputs, link copy implying per-year planning on this screen

### Cele — create / edit

- Fields: name, icon picker, color picker, target amount (optional)
- When target amount provided: planning mode toggle — **Składka miesięczna** vs **Data docelowa**
- Mode `monthly`: editable monthly contribution; show read-only projected completion date
- Mode `by_date`: date picker; show read-only recommended monthly (updates after save via server props)
- Open-ended (no target): hide planning section

### Budżet miesięczny

- Section **Cele**: update **Plan** column source (see metrics); keep saved/released/balance columns
- Link „Zarządzaj celami” → `/goals` (unchanged)
- Hide archived goals by default

### Transfer / transaction forms

- Goal picker unchanged; optionally show goal icon + color in dropdown (enhancement, same release if cheap)

## Migration (existing data)

Run once on deploy:

1. Add new columns to `goals` (`icon`, `color`, `target_amount`, `planning_mode`, `monthly_contribution`, `target_date`, `is_archived`) with sensible defaults (`icon = 'target'`, default color from palette, `is_archived = false`).
2. For each goal with a `goal_annual_estimates` row for the **current calendar year** at migration time:
   - `target_amount` = annual amount (if null annual, leave `target_amount` null)
   - `planning_mode` = `monthly`
   - `monthly_contribution` = annual ÷ 12 (bcdiv, scale 2)
3. Goals with only monthly overrides and no annual: use `monthly_contribution` from latest override or average of overrides for current year; set `planning_mode = monthly`; `target_amount` null unless derivable (if sum of 12 overrides exists, optional — prefer annual only for v1 migration).
4. Drop `goal_monthly_estimates` and `goal_annual_estimates` tables.
5. Remove dead code: estimate actions, requests, routes, tests for estimates.

**Default icon/color:** use same default color assignment pattern as new categories if needed (e.g. rotate palette by `sort_order`).

## PRD delta (for implementer)

Update `.docs/prd.md` when implementing:

| Section | Change |
|---------|--------|
| Słownik — Cel | Target sum envelope; optional monthly or deadline planning; not year-scoped |
| §5 Goal model | Replace estimate tables with new fields |
| FR-G2 | Replace „szacunki roczne/miesięczne” with target amount + planning mode + computed metrics |
| FR-G5 / FR-C5 | Plan column sources from goal fields; cumulative progress on goals screen |
| §3.4 telemetry | Remove `goal_estimate_*` events; add `goal_archived`, `goal_unarchived` if desired |
| §7 Cele screen | CRUD + icon/color + progress; no year selector |

## Backend changes (implementation hint)

| Area | Change |
|------|--------|
| Migration | Add columns; data migration; drop estimate tables |
| `Goal` model | New fillable/casts; remove estimate relations |
| `StoreGoalRequest` / `UpdateGoalRequest` | New fields; mutual exclusivity validation |
| `GoalResource` | Include cumulative metrics, computed fields |
| `ListGoals` | Load cumulative balance; filter archived |
| `Support/Goals/GoalBalance` (new) | Cumulative balance query |
| `Support/Goals/GoalPlanningProjection` (new) | recommended_monthly, projected_completion_date |
| `GoalPlanAmount` | Remove or replace with planning projection helper |
| Monthly budget Action | Plan column from goal fields, not estimates |
| `GoalController` | Remove estimate endpoints; add create/edit Inertia pages |
| Frontend | Refactor `goals/Index.vue`; add Create/Edit; progress bar component |
| Tests | Update Goals, GoalEstimates → GoalPlanning; MonthlyBudget goal plan source |

## Testing

- Feature: CRUD with icon/color; validation mutual exclusivity; archive/unarchive
- Feature: cumulative balance across multiple months; progress bar percent
- Feature: `by_date` recommended monthly decreases as user saves; increases when behind schedule
- Feature: `monthly` mode keeps declaration fixed; projected date shifts when savings lag
- Feature: open-ended goal — balance shown, no progress bar
- Feature: completed badge at 100%; still accepts transfers; balance can exceed target
- Feature: migration from annual estimates
- Feature: monthly budget Plan column uses new sources
- Feature: delete still blocked with linked transactions

## Out of scope (this spec)

- Yearly budget goals rollup
- Charts / sparklines
- Per-goal transfer history page
- Auto-archive on completion
- Linking goal to specific Savings account
- Push notifications / hard limits
- Changing FR-G3 transfer rules

## Self-review checklist

- [x] No TBD placeholders
- [x] Consistent with brainstorming decisions (A/A/C/A/A+manual archive)
- [x] Scope fits single implementation plan
- [x] Migration path from shipped estimate model defined
- [x] `by_date` and `monthly` formulas explicit
- [x] Open-ended and completed edge cases covered

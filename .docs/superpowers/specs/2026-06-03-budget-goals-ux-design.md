# Budget UX refactor + savings goals (cele) — design spec

**Status:** Approved in brainstorming (2026-06-03)  
**Builds on:** `.docs/superpowers/specs/2026-06-03-categories-budget-estimates-design.md` (wave 2, shipped on `feat/categories`)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Canonical requirements target:** `.docs/prd.md` (delta sections listed below)

## Summary

Refactor the **frontend information architecture** so planning and tracking live on **Budget** screens, while **Categories** remain a pure transaction label catalog (sidebar, no amounts). Add a new **Goals (cele)** domain for envelope-style savings (e.g. “200 PLN/month for vacation”), tracked primarily through transfers involving savings accounts (flow A: save → release to checking → spend on checking).

Wave 2’s single “Oszczędności” category + aggregate transfers section is **replaced** for product UX by per-goal tracking. P&L categories and estimates remain; savings planning moves to goals.

## Decisions log

| Topic | Decision |
|-------|----------|
| P&L plan editing | **Budget only** — annual on yearly view (editable); monthly overrides on monthly view |
| Categories screen | **Sidebar item** — CRUD + reorder only; **no** estimate fields or year selector |
| Goals (“cele”) | **Separate sidebar item** — CRUD + estimates; not mixed into Categories |
| Goal vs category | **Separate entity** (`goals` table); categories stay `income` \| `expense` for P&L |
| Savings flow | **Flow A** — transfer to savings (assign goal) → transfer back to checking (assign goal) → expense on checking (optional goal link) |
| Goal on transfer | **Required** when either leg is a `Savings` account; hidden/disabled for ROR↔ROR |
| Goal on expense | **Optional** — links vacation spending to the envelope for reporting |
| Transfer category | Keep required `category_id` on both legs (default system “Oszczędności”); **goal tracking uses `goal_id`**, not category |
| Monthly budget — savings | **Replace** single “Transfery i oszczędności” block with **per-goal table** (plan / saved / released / balance) |
| Yearly budget — goals | **Optional v1** — monthly budget + goals index is primary; yearly goals rollup can follow in same release if cheap |
| Plan terminology | **Szacunki** (soft targets); overrun allowed |
| Architecture | New domain **`Goals`** (Variant A): `Actions/Goals/`, `Http/Controllers/Goals/`, etc. |

## Information architecture (front)

### Sidebar (main nav)

| Item | Purpose |
|------|---------|
| Konta | unchanged |
| Transakcje | unchanged |
| **Budżet** | P&L plan vs actual (monthly + yearly) |
| **Kategorie** | P&L label catalog only |
| **Cele** | Savings envelopes — CRUD + plan amounts |

Settings (`/settings/*`) — profile/password/appearance only; **categories do not move to Settings**.

### Screen responsibilities

#### Kategorie (`/categories`)

- List expense and income categories (sections).
- Add, rename, reorder, delete (existing rules: no delete with transactions; no delete system rows).
- **Remove:** year selector, annual estimate inputs, link copy implying budget setup lives here.

#### Budżet — roczny (`/budget/yearly`)

- Per P&L category: **editable** annual estimate, actual (year), difference.
- Read-only for goals in v1 **or** optional second table “Cele — podsumowanie roku” if included in scope.
- Link: “Zarządzaj kategoriami” → `/categories`.

#### Budżet — miesięczny (`/budget/monthly`)

- Per P&L category: editable monthly plan (override or `annual ÷ 12`), actual, difference.
- **Section “Cele”:** one row per goal — plan, saved, released, balance (see metrics below).
- Soft hint: sum of monthly P&L plans vs annual totals (existing `allocation_hint`, P&L only).
- Link: “Zarządzaj celami” → `/goals`.

#### Cele (`/goals`)

- CRUD goals (name, optional description, sort order).
- Per selected year: annual estimate (same pattern as categories today).
- Per goal optional monthly overrides (can mirror categories UX: edit on monthly budget inline, or on goal detail — **prefer inline on monthly budget** for parity with P&L).
- Empty state CTA when no goals.

## Goals domain model

### Goal

| Field | Description |
|-------|-------------|
| `user_id` | Owner |
| `name` | Display name (e.g. “Wakacje”) |
| `sort_order` | Display order in lists |
| `is_archived` | Optional soft-hide (v1: can defer; delete blocked if linked transactions) |

No `type` — goals are not income/expense.

### GoalAnnualEstimate / GoalMonthlyEstimate

Same shape as `CategoryAnnualEstimate` / `CategoryMonthlyEstimate`:

- Annual: `(goal_id, year)` → optional amount  
- Monthly: `(goal_id, year, month)` → optional override; UI default `annual ÷ 12`

### Transaction extension

| Field | Description |
|-------|-------------|
| `goal_id` | Nullable FK → `goals.id` |

**Rules:**

- `goal_id` set only for user’s goals.
- **Transfer** involving at least one `Account.type = Savings`: `goal_id` **required** on **both** legs (same value).
- **Transfer** ROR↔ROR (no Savings): `goal_id` must be null.
- **Income/expense/adjustment:** `goal_id` optional (expense on checking after release — user may link to goal).
- `category_id` remains required independently (P&L category on expense leg may differ from goal name, e.g. goal “Wakacje”, category “Rozrywka”).

### Metrics (monthly budget & goals index)

For goal *G* in month *M* (calendar), using `COALESCE(booked_at, date)`:

| Metric | Definition |
|--------|------------|
| **Plan** | monthly override or `annual ÷ 12` or “—” |
| **Saved (odłożono)** | Sum of transfer legs where `goal_id = G`, account is Savings, `amount > 0` (credit to savings) |
| **Released (wypłacono)** | Sum of transfer legs where `goal_id = G`, account is Savings, `amount < 0` (debit from savings) |
| **Balance (saldo celu)** | Cumulative saved − released **within scope shown** (month row: month only; optional running total on goals detail) |
| **Linked expenses (powiązane wydatki)** | Optional column: sum of expenses with `goal_id = G`, `transfer_id IS NULL`, in month — informational only |

Internal transfers (`transfer_id` set) excluded from P&L category actuals (unchanged). Goal metrics **use** transfer legs (with `transfer_id` set) on savings accounts.

## User journey (flow A)

1. User creates goal “Wakacje”, sets plan 200 PLN/month (on goals page or monthly budget).
2. Each month: transfer 200 PLN ROR → Savings, select goal **Wakacje** → Saved += 200.
3. Before trip: transfer 1500 PLN Savings → ROR, goal **Wakacje** → Released += 1500, balance decreases.
4. On trip: expenses on ROR, category e.g. Rozrywka, optionally tag goal **Wakacje** → shows under linked expenses.

## Deprecations / migrations from wave 2 UX

| Wave 2 behavior | New behavior |
|-----------------|--------------|
| Annual estimates edited on `/categories` | Edit on `/budget/yearly` only (same API: `categories.estimates.annual`) |
| Single “Transfery i oszczędności” on monthly budget | Per-goal section driven by `goals` |
| System category “Oszczędności” as savings plan | **Keep** category for default transfer `category_id`; **planning** for savings moves to goals. Category may remain for non-goal transfers or legacy; not used for aggregate budget plan. |
| Transfer form: pick any expense category | Unchanged category picker; **add goal picker** when Savings involved |

Existing data: users with only “Oszczędności” estimates may get a one-time migration prompt to create a default goal “Oszczędności ogólne” copying estimate (implementation plan detail).

## New functional requirements (PRD delta)

### FR-G1 — CRUD celów

- Must; domain **Goals**
- User creates named savings goals, reorders, deletes if no linked transactions.
- Events: `goal_created`, `goal_updated`, `goal_deleted`

### FR-G2 — Szacunki celów (roczne / miesięczne)

- Must; domain **Goals**
- Same rules as FR-C3/C4 but for goals (soft sum mismatch hint).

### FR-G3 — Przypisanie celu do transferu

- Must; domain **Transfers** / **Goals**
- Savings leg requires `goal_id`; both legs share same `goal_id`.
- ROR-only transfer forbids `goal_id`.

### FR-G4 — Opcjonalny cel na wydatku

- Should; domain **Transactions**
- Expense/income forms: optional goal select (filtered to user goals).

### FR-G5 — Widok celów w budżecie miesięcznym

- Must; domain **Budgets**
- Replace FR-C5 transfers section with per-goal metrics table.

### FR-UX1 — Plany P&L tylko na budżecie

- Must; domain **Budgets** / **Categories**
- Remove estimate inputs from categories index; enable on yearly budget.

## Out of scope (this spec)

- Multi-goal split on one transfer (single goal per transfer).
- Automatic match release transfer to following expense.
- Goals tied to a specific savings account (any Savings account counts).
- AI categorization / bank category column mapping (unchanged).
- Hard limits / blocking transactions when goal exceeded.

## Error handling

- Transfer with Savings account and missing `goal_id` → 422.
- Transfer without Savings and non-null `goal_id` → 422.
- `goal_id` not owned by user → 422.
- Delete goal with transactions referencing it → blocked (same as categories).

## Testing (minimum)

- Feature: Goals CRUD + isolation per user.
- Feature: Transfer store/update with savings requires goal; both legs same goal_id.
- Feature: Monthly budget goal metrics (saved/released/balance) with fixtures from flow A.
- Feature: Categories index has no estimate fields; yearly budget saves annual estimate.
- Regression: P&L category actuals still exclude `transfer_id` rows.

## Implementation notes

- Reuse patterns from `CategoryAnnualEstimate`, `SaveAnnualEstimate`, `CategoryPlanAmount` for goals.
- Frontend: move annual estimate inputs from `categories/Index.vue` → `budget/Yearly.vue`; add `goals/Index.vue`; extend `transfers/Create.vue` and transaction forms with conditional goal select.
- Telemetry: `goal_*`, `budget_view_monthly` payload may include goal row counts.

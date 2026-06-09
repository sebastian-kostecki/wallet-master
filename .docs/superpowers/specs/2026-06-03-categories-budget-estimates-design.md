# Categories and budget estimates — design spec

**Status:** Approved in brainstorming (2026-06-03)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Canonical requirements target:** `.docs/prd.md` (sections to add/change listed below)

## Summary

Post-MVP feature adding **transaction categories** and **budget estimates** (szacunki — not hard limits). Two budget views:

- **Monthly** — per-category plan vs actual for the month; includes a **transfers / savings** section for operational cash-flow planning.
- **Yearly** — per-category annual estimate vs actual income/expense for the calendar year; **excludes internal transfers** from aggregates (same rule as transaction list summary).

Pre-release: all transactions get a category via migration; every new save requires `category_id`.

## Decisions log

| Topic | Decision |
|-------|----------|
| Plan vs limit terminology | **Szacunki** (soft targets; overrun allowed) |
| Annual vs monthly plan | **Annual sum is canonical**; optional per-month overrides |
| Monthly sum vs annual | **Soft info only** (option A); relevant early in year, not enforced mid-year |
| Category catalog | **Starter set (B)** + user CRUD; each category may have estimates |
| Estimates on income | **Yes (B)** — both expense and income categories |
| Savings on monthly view | **Category “Oszczędności” + transfers section** (not per-account in v1) |
| Yearly view transfers | **Excluded** from P&L aggregates |
| Category on transaction | **Required (A)** — no persistent “uncategorized” after release |
| Import category | **No bank column mapping**; memory (like FR-I5); fallback = **first category of matching type by `sort_order` (B)** |
| Budget year | **Calendar year** on `COALESCE(booked_at, date)` |
| Architecture | **Separate `Categories` + `Budgets` domain entities** (approach 1) |

## PRD changes (outline)

### Remove from §3.2 Out of scope

- Kategoryzacja transakcji i AI kategorii → move AI to post-v1; base categorization in scope
- Budżetowanie i szacowania → in scope as **szacunki**
- “Raporty” → narrow: **tabular budget views in v1**; charts/exports remain out of scope

### Add to §1 Słownik

- **Kategoria** — user-owned label (`income` | `expense`) with display order.
- **Szacunek roczny** — planned amount per category for a calendar year.
- **Szacunek miesięczny** — optional override for a specific month; may differ from `roczny ÷ 12`.
- **Widok budżetu miesięczny** — plan vs actual per category + transfers section.
- **Widok budżetu roczny** — annual plan vs actual; no transfer aggregates.

### Extend §5 Model domeny

**Category**

| Field | Description |
|-------|-------------|
| `user_id` | Owner |
| `name` | Display name |
| `type` | `income` \| `expense` |
| `sort_order` | List order; import fallback uses first matching type |
| `is_system` | Starter / system rows (e.g. “Oszczędności”) — non-deletable in v1 |

**CategoryAnnualEstimate**

| Field | Description |
|-------|-------------|
| `category_id`, `year` | Unique per pair |
| `amount` | Optional; null = no annual plan |

**CategoryMonthlyEstimate**

| Field | Description |
|-------|-------------|
| `category_id`, `year`, `month` | 1–12; unique per triple |
| `amount` | Optional override; null row = UI shows `annual ÷ 12` when annual set |

**Transaction** — add required `category_id` (FK → categories).

### New functional requirements

#### FR-C1 — CRUD kategorii + zestaw startowy

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Categories |

**Zachowanie**  
User manages categories. On first use, seed starter categories (expense + income + system **Oszczędności**). User can add, rename, reorder. Cannot delete category with linked transactions (v1). Cannot change `type` when transactions exist.

**Kryteria akceptacji**

1. Given new user When first access to categories/budget Then starter set exists.
2. Given category with transactions When delete Then blocked with validation error.
3. Given “Oszczędności” system category When delete Then blocked.

**Zdarzenia:** `category_created`, `category_updated`, `category_archived` (if implemented later)

---

#### FR-C2 — Kategoria wymagana na transakcji

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Transactions |

**Zachowanie**  
Manual create/edit, transfer, and import rows require `category_id` belonging to the user. Category `type` must match transaction economic type for `income` / `expense` (from amount sign / `TransactionType`). Transfer form: single category applied to **both** legs. `adjustment` requires a category.

**Kryteria akceptacji**

1. Given create transaction When missing category Then 422.
2. Given expense transaction When income category Then 422.
3. Given transfer When saved Then both legs share same `category_id`.

**Zdarzenia:** (existing transaction events; optional `transaction_category_changed`)

---

#### FR-C3 — Szacunki roczne

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**  
User sets optional annual estimate per category per calendar year. Overrun vs actual is informational only.

**Kryteria akceptacji**

1. Given category and year When save annual estimate 12000 Then yearly view shows plan 12000.
2. Given actual 13000 When yearly view Then shows execution 13000 and difference +1000 without error.

---

#### FR-C4 — Szacunki miesięczne (nadpisania)

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**  
User may override plan for any month. If no override, monthly view displays `annual_estimate ÷ 12` when annual exists. Sum of 12 monthly overrides may differ from annual — UI shows soft hint (no blocking).

**Kryteria akceptacji**

1. Given annual 5000 and override month 3 = 1500 When March budget view Then plan 1500 for that category.
2. Given overrides summing to 4200 and annual 5000 When January Then optional hint “4200 / 5000” without blocking save.

---

#### FR-C5 — Widok budżetu miesięczny

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**  
For selected month: table of categories (income and expense sections) with monthly plan, actual, difference. Actuals use `COALESCE(booked_at, date)` in month. Actuals exclude rows with `transfer_id` for category P&L (same as FR-T2 summary). **Transfers section:** plan from “Oszczędności” category monthly estimate; execution = sum of transfers in month (v1: primarily to `Account.type = Savings`); difference informational.

**Kryteria akceptacji**

1. Given expense in category Food in month When budget monthly Then counts in Food actual.
2. Given internal transfer When monthly category table Then not counted in category expense actuals.
3. Given savings estimate 2000 and transfers 1500 When monthly transfers section Then shows plan 2000, actual 1500.

**Zdarzenia:** `budget_view_monthly`

---

#### FR-C6 — Widok budżetu roczny

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**  
For selected calendar year: per category annual plan vs actual income/expense. **No** transfers section; **no** monthly override breakdown. Aggregates: `transfer_id IS NULL`; include `adjustment` by amount sign (align with FR-T2).

**Kryteria akceptacji**

1. Given year filter When yearly view Then sums Jan–Dec booked period per category without transfers.
2. Given monthly savings plan When yearly view Then savings category shows **actual** spend/income only, not transfer plan section.

**Zdarzenia:** `budget_view_yearly`

---

#### FR-C7 — Pamięć kategorii przy imporcie

| Field | Value |
|-------|-------|
| **Priorytet** | Must |
| **Domena** | Import |

**Zachowanie**  
Do **not** map bank CSV category column. After commit, assign `category_id` via description memory (same key strategy as FR-I5: user + bank + normalized description). On hit, use stored category. On miss, assign first category of matching `income`/`expense` type by `sort_order`. Typesense/search outage must not fail import.

**Kryteria akceptacji**

1. Given prior manual categorization of imported row When re-import same normalized description Then same category.
2. Given no memory hit When import expense row Then first expense category by sort_order.
3. Given mBank file with Kategoria column When import Then column ignored for category assignment.

**Zdarzenia:** `category_memory_hit`, `category_memory_miss`

---

#### FR-C8 — Kategoria na liście transakcji (Should)

| Field | Value |
|-------|-------|
| **Priorytet** | Should |
| **Domena** | Transactions |

**Zachowanie**  
Transaction index shows category name; optional filter by category.

---

### Navigation (§7 UX)

- New top-level item: **Budżet** with Monthly / Yearly toggle.
- **Kategorie** management: from Budżet settings or sub-route.
- Transaction Create/Edit/Transfer: required category select.

## Data architecture (Variant A)

| Layer | Components |
|-------|------------|
| Models | `Category`, `CategoryAnnualEstimate`, `CategoryMonthlyEstimate`; `Transaction.category_id` |
| Actions | `Categories/*` CRUD; `Budgets/ListMonthlyBudget`, `ListYearlyBudget`; extend write transaction actions |
| Integrations | Extend description memory repository for `category_id` (parallel to subject/description) |
| HTTP | `Controllers/Categories`, `Controllers/Budgets`; Form Requests; Resources |
| Support | Optional pure helpers for plan display (`monthlyDisplayPlan`) |

## Aggregation rules (canonical)

**Period field:** `COALESCE(booked_at, date)` — consistent with FR-T2.

**Yearly / monthly category actuals (P&L):**

```sql
WHERE transfer_id IS NULL
  AND type IN ('income', 'expense') -- plus adjustment handling per sign, match ListTransactions summary
```

**Monthly transfers section:**

```sql
WHERE transfer_id IS NOT NULL -- or type = 'transfer'
-- v1 execution metric: legs crediting accounts with type Savings (configurable filter)
```

## Migration (pre-release)

1. Seed categories per existing user (or copy template on first login).
2. Backfill `category_id` on all transactions: by type, assign first category of that type by `sort_order` (interim); user refines via memory over time.
3. NOT NULL `category_id` after backfill.

## Edge cases

| Case | Rule |
|------|------|
| No annual estimate | Show actual only; plan column “—” |
| No monthly override | Display `annual ÷ 12` if annual set |
| Delete category with txs | Block in v1 |
| Change category type | Block if transactions linked |
| Deleted account transactions | Read-only; no category edit |
| Import memory down | Fallback category; import succeeds |
| Transfer | Same category both legs; excluded from P&L tables |
| Booked_at moved across months | Budget aggregates follow new period |

## Out of scope (v1)

- AI category suggestions
- Bank CSV category column mapping
- Charts and budget export
- Per-savings-account estimates (approach B)
- Bulk category edit on index
- Hard alerts on overrun
- Non-calendar fiscal year
- i18n for category names (UI Polish only, as today)

## Testing

| Suite | Coverage |
|-------|----------|
| `tests/Feature/Categories/` | CRUD, isolation, delete guards, sort_order |
| `tests/Feature/Budgets/` | Monthly/yearly aggregates, transfer exclusion, savings section |
| `tests/Feature/Transactions/` | Required category, type matching |
| `tests/Feature/Imports/` | Category memory hit/miss, fallback, no bank column |
| Migration test | All transactions have category after up |

Run: `./vendor/bin/sail artisan test --compact --filter=Categories` (and Budgets) per project workflow.

## Telemetry (add to PRD §3.4)

| Event | FR |
|-------|-----|
| `category_created`, `category_updated` | FR-C1 |
| `category_estimate_annual_saved`, `category_estimate_monthly_saved` | FR-C3, FR-C4 |
| `budget_view_monthly`, `budget_view_yearly` | FR-C5, FR-C6 |
| `category_memory_hit`, `category_memory_miss` | FR-C7 |

## Open points for implementation plan

1. Exact starter category list (Polish names) — product decision in plan task.
2. Transfer form default category = “Oszczędności” vs last used.
3. Monthly transfers metric: only to `Savings` accounts vs all internal transfers — spec recommends Savings-first.
4. Whether FR-C8 is Must or Should for first release slice.

## Self-review (2026-06-03)

- [x] No TBD placeholders blocking implementation
- [x] Consistent: yearly excludes transfers; monthly has transfers section
- [x] Aligns with existing `ListTransactions` summary and `booked_at` rules
- [x] Import: no bank category column; memory + fallback documented
- [x] Scope bounded for single implementation plan

# Transfer category decoupling — design spec

**Status:** Approved in brainstorming (2026-06-04)  
**Supersedes (partial):** transfer-category rows in `.docs/superpowers/specs/2026-06-03-budget-goals-ux-design.md` and `.docs/superpowers/specs/2026-06-03-categories-budget-estimates-design.md`  
**Builds on:** goals + categories wave 2 (shipped)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Canonical requirements target:** `.docs/prd.md`

## Summary

Remove the system **Oszczędności** expense category and decouple **P&L categories** from **internal transfers**. Categories label external cash flows only (`income`, `expense`, `adjustment`). Transfers always have `category_id = null`; savings envelope tracking uses **`goal_id`** exclusively.

## Problem

Wave 2 introduced a system category „Oszczędności” as a placeholder because `category_id` was required on every transaction, including transfers. Goals (cele) replaced savings *planning*, but the category remained as a technical default on transfer legs. This contradicts the mental model: saving money is an **internal movement**, not an external P&L expense. The category also appeared in expense pickers and on the transaction list for transfers without contributing to budget P&L (transfers are already excluded from category actuals).

## Decisions log

| Topic | Decision |
|-------|----------|
| Category scope | **P&L external flows only** — `income`, `expense`, `adjustment` |
| Transfer `category_id` | Always **`null`**; API rejects non-null on transfer create/update/link |
| System „Oszczędności” category | **Removed** from starter set; deleted for existing users after migration |
| Savings tracking | **`goal_id` only** on transfers involving `Savings` (FR-G3 unchanged) |
| Transfer form UI | **No category picker**; goal picker when Savings involved |
| Transaction list | Transfers show **transfer label + optional goal**, not category badge |
| Category filter | Transfers excluded naturally (`category_id IS NULL`) |
| Unlink transfer | Restore `income`/`expense` type; **assign fallback** `category_id` (first of matching type by `sort_order`, same as import miss) |
| Import matcher link | After auto/manual link → set `category_id = null` on both legs |
| Import row (pre-link) | `category_id` still required on commit (income/expense); cleared when linked as transfer |
| Adjustment | `category_id` **still required** |
| `is_system` categories | **None** in starter set v1 (no system rows after migration) |

## Data model

### Transaction — `category_id` rules

| `type` / state | `category_id` |
|----------------|---------------|
| `income`, `expense` | Required; type must match amount sign |
| `adjustment` | Required |
| `transfer` (or `transfer_id` set) | **Must be null** |
| Import row before matcher | Assigned per FR-C7; cleared when linked as transfer |

**Invariant:** if `transfer_id IS NOT NULL` OR `type = transfer` → `category_id IS NULL`.

### Category catalog

- Starter: ~20 expense + ~5 income categories (rich seed); **no** „Oszczędności”, **no** `is_system = true` rows.
- Users may still create custom categories; none are system-protected in v1 after this change.

## Migration (existing data)

Run once on deploy:

1. **Transfers:** `UPDATE transactions SET category_id = NULL WHERE type = 'transfer' OR transfer_id IS NOT NULL`.
2. **Miscategorized P&L:** For `income`/`expense`/`adjustment` rows still pointing at „Oszczędności” category → reassign to first category of matching `type` by `sort_order` (per user).
3. **Estimates:** Delete `CategoryAnnualEstimate` / `CategoryMonthlyEstimate` for „Oszczędności” categories (goal migration `MigrateLegacySavingsEstimate` already copied amounts where applicable).
4. **Category row:** Delete user categories where `is_system = true AND name = 'Oszczędności'` (no remaining FK references after steps 1–2).

## UI / UX

### Transfer create (`/transfers/create`)

- Remove category field and `default_category_id` prop.
- Keep: accounts, amount, date, description, subject, goal (when Savings involved).

### Transaction list

- Transfer rows: omit category badge; show transfer indicator; show goal name when `goal_id` set.
- P&L rows: unchanged (category badge).

### Categories index

- No system badge rows for „Oszczędności” after migration.

## Backend changes (implementation hint)

| Area | Change |
|------|--------|
| `StoreTransferRequest` | Drop `categoryIdRules()`; validate `category_id` absent or null |
| `CreateTransfer` | Do not persist `category_id` |
| `UnlinkTransfer` | After type restore, set fallback `category_id` via `DefaultCategoryId` |
| `TransferMatcher` / `ConfirmTransferCandidate` | On link: `category_id = null` |
| `DefaultCategoryId` | Remove `TransactionType::Transfer` branch |
| `CategoryDefaults` | Remove „Oszczędności” row |
| `TransferController` | Remove `default_category_id` |
| Resources | Nullable category on transfer rows in list/edit |

## PRD delta (FR changes)

### FR-C1

- Starter set: expense + income only; **no** system „Oszczędności”.
- Remove acceptance criterion for blocking delete of system „Oszczędności” (or replace: no system categories in seed).

### FR-C2

- Required on `income`, `expense`, `adjustment`, and **import rows** (pre-transfer).
- **Not** on transfer: both legs `category_id = null`.
- New AC: Given transfer When `category_id` provided Then 422.
- Remove AC: „Given transfer When zapis Then obie nogi mają ten sam `category_id`”.

### FR-T3

- Remove category from transfer behavior and form defaults.
- New AC: Given transfer When saved Then both legs have `category_id = null`.
- Unlink: both legs receive fallback category matching restored type.

### FR-I6 (matcher)

- Add: Given linked pair When commit/link Then `category_id = null` on both legs.

### FR-C6

- Remove criterion referencing „Oszczędności” category plan on yearly view.

### FR-G3, FR-G5

- Remove references to default „Oszczędności” `category_id` on transfers.

## Testing

- Transfer create without category — 201; with `category_id` — 422.
- Savings transfer still requires `goal_id`.
- Unlink assigns fallback categories; both legs valid P&L.
- Matcher auto-link clears categories.
- Migration: no „Oszczędności” category per user; transfer rows null category.
- Monthly budget goal metrics unchanged.
- Category filter excludes transfers.

## Out of scope

- Nullable `category_id` on adjustment (still required).
- Yearly goals rollup changes.
- Lokaty / external savings instruments (future: still account movements, not P&L categories).

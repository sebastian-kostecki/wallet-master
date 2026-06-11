# Deposits (lokaty) — design spec

**Status:** Approved in brainstorming (2026-06-11)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Related:** `.docs/prd.md` (new FR scope — extend PRD when implementing), `2026-06-04-transfer-category-decoupling-design.md` (out-of-scope note on lokaty)

## Summary

Add **term deposits (lokaty)** as a new product concept separate from bank **Accounts** (`checking` / `savings`). A deposit is a single placement: principal, open date, optional maturity date, lifecycle `active` → `closed`. Capital movements appear as **one-legged** transactions on a real bank account (typically ROR); they are **excluded from budget P&L** like internal transfers. **Interest** on maturity is a normal **income** transaction with `category_id` (included in budget).

Supports **manual** create/close/assign and **import auto-detection** per bank (BNP Paribas `Typ transakcji`, mBank `Kategoria` + rules). Import auto-opens deposits and auto-closes when exactly one active deposit matches principal.

UI label (PL): **Lokaty**. Code domain and tables: **Deposits**.

## Decisions log

| Topic | Decision |
|-------|----------|
| vs Accounts / Savings | **Separate** — not a bank account; no statement import on deposit |
| vs Pockets | **Independent** — pockets track envelope goals on Savings accounts; deposits are bank products |
| Entity lifecycle | **One deposit = one placement**; after close, new placement = new record |
| Budget — principal | **Excluded** (like transfer) |
| Budget — interest | **Included** as P&L income with category |
| Table names | `deposits`, `deposit_movements` |
| Domain namespace | `Deposits` (Variant A) |
| Import — open | **Auto** create deposit + movement |
| Import — close principal | **Auto** close when one active deposit matches `principal` |
| Import — ambiguous close | Banner for manual assign (not default P&L income) |
| Manual retroactive | User can mark existing transaction as deposit open/close |
| Net worth UI | Third line on accounts summary: “Lokaty (PLN)” |

## Problem

Users hold money in term deposits (lokaty) that are not transactional bank accounts. Bank statements show:

1. **Opening:** outgoing on ROR (e.g. BNP `OTWARCIE LOKATY`)
2. **Closing:** incoming principal (e.g. BNP `SPŁATA DEPOZYTU`)
3. **Interest:** separate incoming line (normal income)

Today these import as categorized P&L and distort budget. Transfers require two **accounts**; deposits have only one bank leg — the other “leg” is virtual deposit balance.

## Architecture choice

**Chosen:** New domain `Deposits` with `deposits` + `deposit_movements` tables.

**Rejected:**

- `AccountType::TermDeposit` — contradicts “not a bank account”; blurs Savings; forces fake second account for transfer pattern.
- Reuse `transfer_id` only — transfers are two account transactions; deposits are single-account capital movements.
- `Instruments` abstraction in v1 — premature; rename/migrate when a second instrument type ships.

## Data model

### `deposits`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK users | scope |
| `currency_id` | FK currencies | MVP: PLN in UI |
| `name` | string nullable | e.g. “3M mBank” |
| `principal` | decimal(15,2) | capital amount |
| `opened_at` | date | placement date |
| `matures_at` | date nullable | optional after auto-import; user can fill later |
| `status` | enum string | `active`, `closed` |
| `closed_at` | date nullable | set on close |
| `bank` | enum nullable | `BnpParibas`, `MBank` — product source hint |
| timestamps | | |

Indexes: `(user_id, status)`, `(user_id, currency_id, status)`.

### `deposit_movements`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `deposit_id` | FK deposits | |
| `transaction_id` | FK transactions | unique |
| `kind` | enum string | `open`, `close_principal` |
| timestamps | | |

One transaction links to at most one `deposit_movement`. Interest transactions have **no** `deposit_movement` row.

### Transaction rules (capital)

| Field | Open (`-X` on ROR) | Close principal (`+X`) | Interest (`+Y`) |
|-------|--------------------|--------------------------|-----------------|
| `type` | from amount sign (`expense` / `income`) | from amount sign | `income` |
| `category_id` | `null` | `null` | **required** (FR-C2) |
| `transfer_id` | `null` | `null` | `null` |
| `deposit_movements` row | `kind=open` | `kind=close_principal` | none |
| Budget P&L | excluded | excluded | included |

### Budget / summary exclusion

Introduce a shared query constraint (e.g. `Transaction::scopeExcludedFromBudget()` or `Support/Transactions/BudgetExclusionQuery`):

```sql
-- Included in P&L aggregates when:
transfer_id IS NULL
AND NOT EXISTS (
  SELECT 1 FROM deposit_movements dm
  WHERE dm.transaction_id = transactions.id
  AND dm.kind IN ('open', 'close_principal')
)
```

Apply everywhere `transfer_id IS NULL` is used today:

- `ListTransactions` summary
- `BudgetTransactionQuery`
- Yearly/monthly budget actuals (FR-C5, FR-C6)

**Do not** change pocket metrics (still transfer legs on Savings with `pocket_id`).

### Deposit balance (read model)

Active deposits total per currency:

```sql
SELECT SUM(principal) FROM deposits
WHERE user_id = ? AND status = 'active' AND currency_id = ?
```

Computed at read time in Actions — not stored on `accounts`.

## Domain layout (Variant A)

| Layer | Path |
|-------|------|
| Models | `Deposit`, `DepositMovement` |
| Enums | `DepositStatus`, `DepositMovementKind` |
| Actions | `Actions/Deposits/` — `CreateDeposit`, `CloseDeposit`, `ListDeposits`, `AssignTransactionToDeposit`, … |
| Support | `Support/Deposits/DepositCloser` (match active by principal), `Support/Imports/DepositRowClassifier` |
| HTTP | `Http/Controllers/Deposits/`, `Http/Requests/Deposits/`, `Http/Resources/Deposits/` |
| Import hook | `Imports/Workflow/CommitImport` — classify row before/instead of plain P&L insert |
| Routes | `routes/deposits.php` or `routes/web.php` group — URIs `/deposits`, … |
| Frontend | `resources/js/pages/deposits/`, `resources/js/components/deposits/` |
| Tests | `tests/Feature/Deposits/`, import fixtures per bank |

**Not** under `Accounts`, `Pockets`, or `Transfers`.

## User flows

### Manual — open deposit

1. User: `/deposits/create` — source account (ROR), principal, `opened_at`, optional `matures_at`, optional `name`.
2. `CreateDeposit`: DB transaction — create `deposits` (`status=active`), create transaction `-principal` on source account, create `deposit_movements` (`open`), update `current_balance`.
3. Redirect + toast `deposits.toast.created`.

### Manual — close deposit

1. From active deposit detail/list: destination account, `closed_at` (default today).
2. `CloseDeposit`: transaction `+principal` on destination, `deposit_movements` (`close_principal`), `status=closed`, `closed_at` set.
3. User adds interest separately as normal income (or import does).

### Manual — assign existing transaction

1. On transaction list/detail: action “Przypisz do lokaty” / “To ruch lokaty”.
2. User picks: **open new deposit** or **close existing active deposit**.
3. `AssignTransactionToDeposit`: clear `category_id`, create/update `deposits` + `deposit_movements`, fix balances if needed (same rules as create/close).
4. Block if transaction already has `transfer_id` or existing `deposit_movement`.

### Import — classification (per bank)

Extend parsing pipeline to expose bank-specific signals (adapter or classifier on raw row + `Bank`):

| Bank | Signal | Open | Close principal |
|------|--------|------|-----------------|
| BNP Paribas | Column `Typ transakcji` | `OTWARCIE LOKATY` | `SPŁATA DEPOZYTU` |
| mBank | Column `Kategoria` | `Lokaty i konto oszcz.` + negative amount | `Lokaty i konto oszcz.` + positive amount matching principal pattern |

mBank adapter today ignores `Kategoria` for P&L (FR-C7) — reuse column **only** for deposit classification, not for `category_id`.

Add CSV fixtures: `tests/Fixtures/import/bnp-deposit-open.csv`, `bnp-deposit-close.csv`, extend mBank fixture with labeled rows.

### Import — commit behavior

For each row after parse:

1. `DepositRowClassifier::classify($bank, $rawRow, $parsedRow)` → `none` \| `open` \| `close_principal`.
2. **`open`:** auto `CreateDepositFromImport` (no category resolution); skip `ResolveCategoryForImportRow` for this row.
3. **`close_principal`:** `DepositCloser::closeByPrincipal($user, $amount, $date, $account, $transaction)` — if exactly one `active` deposit with matching `principal` (same currency), auto-close; else save transaction excluded from budget but flag as **deposit close candidate** (banner).
4. **`none`:** existing P&L import path (category memory + fallback).

Run `TransferMatcher` **after** deposit processing (unchanged order relative to deposit rows — deposit rows must not enter transfer matcher as P&L).

Interest lines: `none` → normal income import.

### Import — no match on close

If principal return cannot match an active deposit: store transaction with `deposit_movements` pending or `transfer_match_status`-like flag `deposit_close_candidate` (exact field TBD in plan — prefer explicit `deposit_match_status` enum on `transactions` mirroring transfer pattern, or a `deposit_close_candidates` queue table). Show banner on transactions index analogous to FR-I6 transfer candidates.

## UI / IA

**Sidebar:** add **Lokaty** → `/deposits` (between Konta and Transakcje or after Konta — match plan).

**Screens:**

- Index: active / closed tabs; columns: name, principal, opened, matures, status, bank.
- Create / edit: name, principal, dates, bank (optional).
- Close action on active deposit.
- Transaction list: badge “Lokata” on capital movements; assign action on eligible P&L rows.

**Accounts summary card** (`AccountsSummaryCard.vue` or server prop):

- New line: **Lokaty (PLN)** — sum of active deposits in PLN (backend getter from `ListAccounts` or dedicated small query in `AccountController` — prefer Action getter for consistency if deposits summary is added server-side).

## Balance accounting

| Event | ROR balance | Deposits total | Net worth |
|-------|-------------|----------------|-----------|
| Open 10k | −10k | +10k | unchanged |
| Close 10k principal | +10k | −10k | unchanged |
| Interest 150 | +150 | 0 | +150 (P&L) |

Accounts index “Suma sald (PLN)” remains **accounts only**. Lokaty line shows parked capital not on accounts.

## Policies & validation

- `DepositPolicy`: `view`, `update`, `delete` per `user_id`.
- Open: source account active, same currency, amount > 0, sufficient balance optional (warn only or allow negative — align with transfer behavior).
- Close: deposit `active`, destination account active, same currency.
- Delete deposit with movements: blocked (v1).
- Soft-deleted account: block new open/close; existing movements read-only.

## Telemetry (suggested)

| Event | When |
|-------|------|
| `deposit_created` | manual open |
| `deposit_closed` | manual or import close |
| `deposit_import_open_auto` | import open |
| `deposit_import_close_auto` | import matched close |
| `deposit_import_close_unmatched` | close row, no matching active deposit |
| `deposit_transaction_assigned` | retroactive assign |

Add to PRD §3.4 when implementing.

## Testing

| Area | Tests |
|------|-------|
| Manual open/close | Feature: balances, movements, budget exclusion |
| Assign transaction | Feature: category cleared, movement linked |
| Import BNP open/close | Feature with fixtures |
| Import mBank category | Feature with fixtures |
| Budget actuals | Principal excluded, interest included |
| List transactions summary | Excludes deposit capital |
| Auto-close ambiguity | Two active same principal → candidate, not wrong close |
| Isolation | `user_id` policies |

## Out of scope (v1)

- `Instruments` type enum (bonds, IKE, …)
- Pocket ↔ deposit linking
- Interest auto-classification rules per bank (import as normal income only)
- Multi-currency UI beyond PLN
- Deposit delete / edit principal after open
- Auto-fill `matures_at` from bank data
- PRD formal FR-* IDs (add in implementation PR)

## Self-review checklist

- [x] No TBD placeholders blocking implementation
- [x] Consistent with Variant A domain naming (`Deposits`)
- [x] Budget rules explicit for principal vs interest
- [x] Import per-bank signals documented
- [x] Single scope — one implementation plan
- [x] Close-candidate storage flagged as plan detail (not ambiguous on behavior)

## Implementation notes (for plan)

1. Migration: `deposits`, `deposit_movements`.
2. Models, enums, factories, policies.
3. `BudgetExclusion` refactor — single source for transfer + deposit capital exclusion.
4. Actions + HTTP + Inertia pages (CRUD + close).
5. `DepositRowClassifier` + BNP/mBank rules; extend mBank adapter to pass `Kategoria` in raw snapshot if needed.
6. `CommitImport` integration before category resolution for classified rows.
7. `AssignTransactionToDeposit` + transaction UI action.
8. Deposit close candidate banner (mirror transfer candidates UX).
9. Accounts summary “Lokaty (PLN)” line.
10. PRD + checklist update; i18n `deposits.*`, sidebar link.

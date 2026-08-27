# Term deposits (lokaty) — design spec

**Date:** 2026-08-27  
**Status:** Approved in brainstorming  
**Scope:** v1 manual deposits — third tab on `/konta`, one-legged capital on bank accounts, close with optional interest income, assign existing transactions. No CSV auto-classification.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-accounts-wallets-tabs-design.md` (`?kind=` tabs); `.docs/superpowers/specs/2026-08-26-accounts-modals-design.md`; `.docs/superpowers/specs/2026-08-27-dashboard-operator-overview-design.md` (KPI row)  
**Related:** `.docs/prd.md` (add FR scope when implementing); `.docs/superpowers/specs/2026-06-11-deposits-design.md` (**superseded**); `.docs/superpowers/plans/2026-06-11-deposits.md` (stale — do not execute; write a new plan from this spec)

## Goal

Track **term deposits (lokaty)** as parked capital that leaves a bank account (ROR or savings), sits until maturity, and returns with optional interest. Principal must not distort budget P&L. Interest is extra **income** with a category, linked to the deposit.

UI label (PL): **Lokaty**. Code domain and tables: **Deposits**. Not an `Account` and not a pocket.

## Decisions log

| Topic | Decision |
|-------|----------|
| IA | Third tab on `/konta` (`?kind=deposits`), same sidebar node as Rachunki / Portfele |
| vs Accounts | **Separate entity** — not `AccountType`; no statement import on the deposit |
| vs transfer | **One-legged** on a real bank account; `transfer_id` always null |
| Lifecycle | One deposit = one placement; fixed `principal`; close is always 100% |
| Open / return accounts | Bank accounts only (`bank !== cash`); checking **or** savings |
| Close | One action: return principal + optional interest income (category required if interest > 0) |
| Interest link | `deposit_movements.kind = interest`; at most one interest movement per deposit |
| v1 capture | Manual create/close **and** assign existing transactions |
| Import CSV classifier | **Out of v1** (BNP/mBank auto-open/close stays for a later spec) |
| Combined bank line (principal+interest) | **Out of v1** — assign close requires amount = `principal` |
| Dashboard | Fifth KPI **Lokaty (PLN)**; `total_pln` stays accounts only |
| Delete | Not in v1 (create always writes an `open` movement) |

## Approaches considered

1. **Deposits domain + tab on `/konta?kind=deposits`** (chosen) — matches “next to Rachunki / Portfele”; write URIs stay on `/deposits`.
2. Tab chrome with a separate `/lokaty` route — cleaner controller split, weaker “part of Konta” IA.
3. Lokata as an account you transfer into — rejected: fake second account, clashes with savings, breaks import later.

## Current state

- `/konta` has tabs Rachunki / Portfele (`kind=accounts|wallets`). `AccountType` is `checking` \| `savings`. Cash wallets are `bank === cash`.
- Internal transfers use two legs and `transfer_id`. Pockets attach to transfers (`pocket_id` + `pocket_movement`).
- Budget P&L and transaction summary exclude `transfer_id IS NOT NULL` (`ListTransactions`, `BudgetTransactionQuery`; dashboard cashflow uses the same query). `TransferMatcher` also selects unpaired rows with `whereNull('transfer_id')`.
- Dashboard operator overview: four KPIs (account PLN total, savings PLN, month income, month expense).
- June 2026 deposits spec/plan included import auto-detection; **not implemented**.

## Target behavior

### Data model

#### `deposits`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK users | |
| `currency_id` | FK currencies | MVP UI: PLN |
| `source_account_id` | FK accounts | Account used on open; default destination on close |
| `name` | string nullable | Empty → display fallback, not stored as generated junk if user omitted (Resource: name or i18n fallback) |
| `principal` | decimal(15,2) | Frozen after open |
| `opened_at` | date | |
| `matures_at` | date nullable | |
| `status` | string enum | `active`, `closed` |
| `closed_at` | date nullable | Set on close |
| timestamps | | |

Indexes: `(user_id, status)`, `(user_id, currency_id, status)`.

No `bank` column — derive bank from `source_account` when needed.

#### `deposit_movements`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `deposit_id` | FK deposits | |
| `transaction_id` | FK transactions | **unique** |
| `kind` | string enum | `open`, `close_principal`, `interest` |
| timestamps | | |

One transaction → at most one movement. Unique `(deposit_id, kind)` so each deposit has at most one `open`, one `close_principal`, and one `interest`.

### Transaction rules

| Event | Account | Amount | `category_id` | `transfer_id` / `pocket_id` | Budget |
|-------|---------|--------|---------------|------------------------------|--------|
| Open | source (bank) | `−principal` | `null` | `null` | excluded |
| Close principal | destination (bank) | `+principal` | `null` | `null` | excluded |
| Interest | destination of the close, or the income account on assign | `+interest` | **required** | `null` | **included** |

Manual close uses the destination account for both principal return and interest (same account, two transactions). Assign-interest may point at an existing income on any **bank** account of the user in the same currency.

`Transaction.type` follows amount sign (`expense` / `income`) as today.

### Budget / matcher exclusion

Shared constraint (scope on `Transaction` or `Support/Transactions/BudgetExclusionQuery`):

- Included in P&L when `transfer_id IS NULL` **and** the transaction has **no** `deposit_movements` row with `kind IN ('open', 'close_principal')`.
- Interest movements are P&L (they have `kind = interest`).

Apply everywhere unpaired / P&L rows are selected today:

- `ListTransactions` summary
- `BudgetTransactionQuery` (monthly/yearly actuals, dashboard cashflow)
- `TransferMatcher` candidate pool — capital deposit legs must not be offered as transfer matches

Do **not** change pocket metrics.

### Deposit balance (read model)

Active total per currency, computed in Actions (not stored on `accounts`):

```sql
SELECT SUM(principal) FROM deposits
WHERE user_id = ? AND status = 'active' AND currency_id = ?
```

Accounting identity: open −10k on ROR, deposits total +10k, net worth unchanged; close reverses capital; interest +150 increases ROR and P&L.

### Domain layout (Variant A)

| Layer | Path |
|-------|------|
| Models | `Deposit`, `DepositMovement` |
| Enums | `DepositStatus`, `DepositMovementKind` |
| Actions | `Actions/Deposits/` — `CreateDeposit`, `CloseDeposit`, `UpdateDepositDetails`, `ListDeposits`, `AssignTransactionToDeposit` |
| Support | `Support/Transactions/` — shared P&L exclusion used by budget + list + matcher |
| HTTP | `Http/Controllers/Deposits/DepositController.php` (store/update/close); assign on transactions controller or a small `DepositAssignmentController` |
| Requests / Resources | `Http/Requests/Deposits/`, `Http/Resources/Deposits/` |
| Routes | Write: `routes/deposits.php` (or web group). **Index URL unchanged:** `accounts.index` + `kind=deposits` |
| Frontend | Tab + list in `pages/accounts/Index.vue`; `components/deposits/`; assign action on transaction row |
| Tests | `tests/Feature/Deposits/` |

Form options (`Data/Deposits/DepositFormOptions`): eligible source/destination bank accounts; income categories for close and assign-interest. Actions never import `Http/Resources`. `ListDeposits::handle(User $user): void` + getters (`getDeposits()`, form-option data as needed). `AccountController::index` calls `ListDeposits` **only** when `kind=deposits` (no full accounts grid payload on that tab); Rachunki/Portfele keep the current accounts query.

Write Actions: `handle(User $user, array $validated): Deposit` (close: `handle(User $user, Deposit $deposit, array $validated): Deposit`).

### User flows

#### Manual open

1. Tab Lokaty → **Dodaj lokatę** (modal). Fields: source account (active, `bank !== cash`, user’s currency), principal > 0, `opened_at`, optional name, optional `matures_at`.
2. `CreateDeposit` in one DB transaction: `deposits` (`active`), transaction `−principal` on source (`category_id` null), `deposit_movements` (`open`), update `current_balance`.
3. Redirect `accounts.index?kind=deposits` + toast `deposits.toast.created`.

GET `deposits.create` / `deposits.edit` redirect to that index (same pattern as accounts modals), with `new=1` / highlight as needed.

#### Manual close

1. Active row → **Zamknij**. Fields: destination bank account (same currency; default `source_account_id`), `closed_at` (default today), interest ≥ 0, income `category_id` **required iff interest > 0**.
2. If the deposit already has an `interest` movement (assigned earlier): interest fields hidden; do not create a second interest transaction.
3. `CloseDeposit`: `+principal` (`close_principal`), optional `+interest` (`interest`), `status=closed`, `closed_at`. Block if not `active`.

#### Assign existing transaction

Eligible: user’s transaction; account `bank !== cash` and not soft-deleted; `transfer_id` null; no `deposit_movement`; `type` is not `adjustment`.

| Intent | Amount rule | Effect |
|--------|-------------|--------|
| Open | amount < 0 | Create deposit, `principal = abs(amount)`, `opened_at` from transaction date, `source_account_id` = transaction account, clear `category_id`, movement `open` |
| Close principal | amount > 0 and **equals** chosen active deposit’s `principal` (same currency) | Clear `category_id`, movement `close_principal`, close deposit (`closed_at` = transaction date). Interest stays 0 unless already assigned or assigned later |
| Interest | amount > 0; deposit has **no** interest movement yet | Keep `category_id` (P&L), movement `interest`. Allowed on **active or closed** deposit (same user, same currency) |

Assign open with non-negative amount, close with amount ≠ principal, second interest, or ineligible row → 422.

v1: imported deposit legs stay P&L until the user assigns them. No CSV classifier, no close-candidate banner.

### UI / IA

**Tabs** (`pageRegistry` group `accounts`): Rachunki / Portfele / **Lokaty**. Query `kind=deposits`. Invalid or missing `kind` still defaults to Rachunki (`accountKindFromUrl`). `h1` remains Konta. Sidebar unchanged.

**Lokaty list:** dedicated cards/rows (not `AccountCard`). Columns: name (or fallback), principal, opened, matures or “—”, status. Active first; closed muted below. Shared grid/list toggle with key `deposits.viewMode`. Empty state is **per tab**. CTA: **Dodaj lokatę**.

**Active row menu:** close; edit name + `matures_at` only (`UpdateDepositDetails`). No principal/source change. No delete.

**Transactions:** badge **Lokata** on `open` / `close_principal`. Interest: normal income plus a discreet deposit link. Row action **Przypisz do lokaty** when eligible.

**Dashboard:** fifth KPI `deposits_pln` (sum of active principals in PLN). `total_pln` **unchanged** (accounts + wallets only). Grid `sm:grid-cols-2 xl:grid-cols-5`. Tile links to `accounts.index?kind=deposits`. Month cashflow tiles use the extended P&L exclusion (capital out, interest in).

**i18n:** `deposits.*`, `accounts.tabs.deposits`, toasts `deposits.toast.*`.

### Policies and validation

- `DepositPolicy`: `view` / `update` / `close` (and assign via transaction policy + deposit ownership). Scope by `user_id`.
- Open: source active, `bank !== cash`, same currency as selected (MVP PLN).
- Close: deposit `active`; destination active, `bank !== cash`, same currency.
- Assign: rules in the table above; cannot assign another user’s deposit or transaction.
- Soft-deleted account: no new open/close/assign onto it; existing movements remain readable.
- Source balance: **no hard block** on going negative (same as transfers).
- Delete deposit: not exposed in v1.

### Telemetry (suggested)

| Event | When |
|-------|------|
| `deposit_created` | manual open |
| `deposit_closed` | manual close |
| `deposit_updated` | metadata edit |
| `deposit_transaction_assigned` | assign (property: kind) |

Add FR IDs and events to PRD §3.4 when implementing.

### Testing

| Area | Tests |
|------|-------|
| Manual open/close | Feature: balances, movements, optional interest, category null vs required |
| Assign | Feature: three kinds; 422 cases (transfer, cash, amount mismatch, second interest) |
| Budget / summary / dashboard cashflow | Principal excluded; interest included |
| TransferMatcher | Open/close legs not candidates |
| Index tab | `kind=deposits` list; other kinds unchanged |
| Dashboard KPI | `deposits_pln` sum of active only |
| Isolation | `user_id` policies |

## Out of scope (v1)

- CSV / adapter classification (BNP `OTWARCIE LOKATY`, mBank `Kategoria`, auto-close by principal, unmatched banner)
- One statement line that mixes principal and interest
- Top-ups, partial close, renewable “same account” reuse (new placement = new row)
- Pocket ↔ deposit linking
- `Instruments` abstraction
- Multi-currency UI beyond PLN
- Delete / edit principal after open
- Auto-fill `matures_at` from the bank
- New sidebar item `/lokaty`

## Implementation notes (for plan)

1. Migrations: `deposits`, `deposit_movements`; models, enums, factories, policy.
2. Extract shared P&L exclusion; wire `ListTransactions`, `BudgetTransactionQuery`, `TransferMatcher`.
3. Actions + `DepositController` writes + assign endpoint; `AccountController@index` branch on `kind`.
4. Vue: third tab, deposit list/modals, transaction badge + assign, dashboard fifth KPI.
5. PRD + checklist; i18n; Pint + Sail tests `tests/Feature/Deposits/` (and existing budget/list filters).

## Self-review checklist

- [x] No TBD placeholders blocking implementation
- [x] Consistent with Variant A (`Deposits`, Action getters, no Resources in Actions)
- [x] Budget rules explicit for principal vs interest
- [x] v1 vs June spec: import classifier explicitly out
- [x] Close-with-interest vs assign-interest cannot double-create
- [x] Single implementation-plan scope

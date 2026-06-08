# Pocket initial balance — design spec

**Status:** Approved in brainstorming (2026-06-08)  
**Builds on:** pockets target model (`.docs/superpowers/specs/2026-06-04-goals-target-model-design.md`), pockets currency (`.docs/superpowers/specs/2026-06-05-goals-currency-design.md`)  
**Canonical requirements target:** `.docs/prd.md` (extends FR-P1)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)

## Summary

Add an optional **initial balance** (`initial_balance`) on pockets, set **only at create time**. It represents money the user has already saved toward a goal before tracking transfers in the app. It adjusts cumulative pocket metrics (balance, progress, planning projections) without creating transactions, transfers, or changes to bank account balances.

## Problem

Pocket balance is computed exclusively from Savings transfer legs with `pocket_id`. Users who already deposited money toward a goal (or tracked savings before creating the pocket) see **0 balance** until they record new transfers. There is no way to bootstrap progress on Create/Edit forms.

## Decisions log

| Topic | Decision |
|-------|----------|
| Semantics | Metadata offset — “already saved X for this goal”; **no** transfer, **no** account balance change |
| Persistence | New column `pockets.initial_balance` (decimal, default `0`) |
| Create | Optional field on `/pockets/create`; empty = `0` |
| Edit | **Not editable** after create; optional read-only display when `> 0` |
| Balance formula | `balance = initial_balance + saved_total − released_total` (transfer net unchanged) |
| Monthly budget | Monthly saved/released/movement columns **unchanged** (transfers only in period) |
| Planning | `recommended_monthly`, `projected_completion_date`, `is_completed`, progress bar use full `balance` including initial |
| Naming (DB) | `initial_balance` (distinct from account `opening_balance` — pocket has no separate `current_balance`) |
| Migration | Existing pockets backfill `initial_balance = 0` |
| Architecture | Variant A domain `Pockets`; logic in `Support/Pockets/PocketBalance` |

## Data model

### Pocket (updated)

| Field | Type | Description |
|-------|------|-------------|
| `initial_balance` | `decimal(12,2)`, NOT NULL, default `0` | Starting amount for cumulative balance; writable only on create |

All other Pocket fields unchanged (see target model spec).

## Validation

### StorePocketRequest

- `initial_balance`: optional, `numeric`, `min:0`; empty string normalized to `0` in `prepareForValidation` or action layer.

### UpdatePocketRequest

- `initial_balance`: **not accepted** (immutable after create).

## Metrics (`Support/Pockets/PocketBalance`)

### Cumulative balance (updated)

```php
$transferNet = bcsub($savedTotal, $releasedTotal, 2);
$balance = bcadd((string) $pocket->initial_balance, $transferNet, 2);
```

Return shape unchanged: `{ saved_total, released_total, balance }` where `saved_total` / `released_total` still reflect **transfer legs only**; `balance` includes `initial_balance`.

### Monthly net map

**Unchanged** — only transfer legs; initial balance does not appear in any calendar month.

### Computed UI fields

`is_completed`, `progress_percent`, `recommended_monthly`, `projected_completion_date` — all receive the updated `balance` from `cumulative()`; no formula changes in `PocketPlanningProjection`.

## API

### PocketResource

Add:

```php
'initial_balance' => (string) $this->initial_balance,
```

Expose on create response, index, edit (for read-only display). `balance` already includes initial via `PocketBalance::cumulative()`.

## UI

### `pockets/Create.vue`

- New optional field **“Wstępna kwota”** / **“Initial amount”** after currency selector (or before target amount).
- Currency symbol suffix (same pattern as `target_amount`).
- Hint copy: initial amount does not create a transfer or change account balances.
- Default: empty (= 0).

### `pockets/Edit.vue`

- **No** editable field for `initial_balance`.
- When `initial_balance > 0`: read-only line showing the value (optional but recommended for transparency).

### `pockets/Index.vue`

- No changes — `balance` prop already reflects initial.

### Monthly budget pocket section

- No changes — period movement columns remain transfer-based.

## i18n

Add keys under `pockets.fields`:

| Key | PL | EN |
|-----|----|----|
| `initialBalance.label` | Wstępna kwota | Initial amount |
| `initialBalance.hint` | Kwota już odłożona na ten cel. Nie tworzy transferu ani nie zmienia salda kont. | Amount already saved toward this goal. Does not create a transfer or change account balances. |

## PRD extension

Extend **FR-P1** acceptance criteria:

- Given create pocket When optional `initial_balance` = 500 Then list shows cumulative balance including 500 before any transfers.
- Given existing pocket When update Then `initial_balance` unchanged regardless of request payload.

## Testing

| Test | Assertion |
|------|-----------|
| `PocketCrudTest` | Create with `initial_balance`; persisted value |
| `PocketCrudTest` | Create without field → `0` |
| `PocketCrudTest` | Update cannot change `initial_balance` |
| `PocketBalanceTest` | `cumulative()` adds initial to transfer net |
| `PocketBalanceTest` | Monthly net map excludes initial |
| Feature | Progress / `is_completed` uses balance with initial |

## Out of scope

- Editing initial balance after create (user chose option B).
- Synthetic or hidden transactions for opening amount.
- Impact on account `current_balance`.
- Allocating initial balance to a specific month in budget views.
- Negative initial balance (withdrawals before tracking).

## Files (implementation hint)

| Layer | File |
|-------|------|
| Migration | `database/migrations/*_add_initial_balance_to_pockets.php` |
| Model | `app/Models/Pocket.php` |
| Request | `app/Http/Requests/Pockets/StorePocketRequest.php` |
| Action | `app/Actions/Pockets/StorePocket.php` |
| Support | `app/Support/Pockets/PocketBalance.php` |
| Resource | `app/Http/Resources/Pockets/PocketResource.php` |
| Frontend | `resources/js/pages/pockets/Create.vue`, `Edit.vue` |
| i18n | `resources/js/locales/pl.json`, `en.json` |
| Tests | `tests/Feature/Pockets/PocketCrudTest.php`, `tests/Unit/Support/Pockets/PocketBalanceTest.php` |

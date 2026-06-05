# Goals currency — design spec

**Status:** Approved in brainstorming (2026-06-05)  
**Builds on:** goals target model (`.docs/superpowers/specs/2026-06-04-goals-target-model-design.md`)  
**Canonical requirements target:** `.docs/prd.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)

## Summary

Each savings goal stores its own **`currency_id`** (FK → `currencies`), like accounts. MVP UI offers only PLN, but amounts are displayed with currency symbol everywhere (goals list, progress bar, create/edit forms, monthly budget goal section). Cumulative balance and transfer assignment respect goal currency. Multi-currency selection and FX conversions remain out of scope.

## Problem

Goals expose raw decimal strings without currency context (`1500.00`). The data model has no currency on `goals`, so future multi-currency support would require retroactive migration and ambiguous balance aggregation across Savings accounts in different currencies.

## Decisions log

| Topic | Decision |
|-------|----------|
| Currency ownership | **Per goal** — `goals.currency_id` required, set at create |
| MVP UI | Currency field on create/edit; **only PLN** in dropdown (same as accounts) |
| Immutability | **Immutable after create** (parity with accounts — `UpdateAccountRequest` has no `currency_id`) |
| Display | Nested `currency` object in API (`CurrencyResource` shape); frontend formats `amount + symbol` |
| Balance aggregation | `GoalBalance` counts only Savings transfer legs where `account.currency_id = goal.currency_id` |
| Transfer + goal (FR-G3) | `goal.currency_id` must match Savings account `currency_id` on the transfer |
| Optional goal on P&L (FR-G4) | When `goal_id` set, transaction `currency_id` must match goal `currency_id` |
| FX / conversions | **Out of scope** — no cross-currency goal progress |
| Migration | Existing goals → PLN (`currencies.code = 'PLN'`) |

## Data model

### Goal (updated)

| Field | Type | Description |
|-------|------|-------------|
| `currency_id` | FK → `currencies`, NOT NULL | Goal denomination; immutable after create |

All other Goal fields unchanged (see target model spec).

### Relations

`Goal` belongs to `Currency`. User 1—N Goals; Currency 1—N Goals.

## Validation

### StoreGoalRequest

- `currency_id`: required, `exists:currencies,id` (MVP: only PLN seeded).
- Default on create form: PLN pre-selected.

### UpdateGoalRequest

- `currency_id`: **not accepted** (immutable).

### Transfer / transaction guards

- Extend `ValidatesGoalId` (or equivalent): when `goal_id` present and Savings involved, assert `goal.currency_id === savingsAccount.currency_id`.
- FR-G4: assert `goal.currency_id === transaction.currency_id` when `goal_id` on P&L row.

## Metrics (`Support/Goals/GoalBalance`)

All cumulative and monthly queries add:

```php
->whereHas('account', fn ($q) => $q
    ->where('type', AccountType::Savings)
    ->where('currency_id', $goal->currency_id))
```

`GoalTransactionMetrics` — same filter for monthly saved/released/balance.

## API

### GoalResource

Add:

```php
'currency_id' => $this->currency_id,
'currency' => CurrencyResource::make($this->currency)->resolve($request),
```

Eager-load `currency` in list/create/edit actions.

### GoalFormOptions

Add `currencies` list (same query as `AccountController::create` — all seeded currencies; MVP = PLN only).

### Monthly budget `goal_rows`

Include `currency` (or at minimum `currency.symbol`) on each row for consistent frontend formatting.

## Frontend

- Shared helper: `formatMoney(value, currency)` using `Intl.NumberFormat('pl-PL', …)` + `currency.symbol` (fallback `t('currency.defaultSymbol')`).
- **Create/Edit:** currency dropdown (disabled on edit); suffix on amount inputs.
- **Index / GoalProgressBar:** formatted amounts with symbol.
- **Monthly budget goal section:** use row currency for all money columns.

Reuse patterns from `accounts/Create.vue` and `AccountCard.vue`.

## Migration

1. Add `currency_id` nullable column on `goals`.
2. Backfill from PLN currency id.
3. Set NOT NULL + FK constraint.

## Testing

- Feature: create goal with PLN; resource includes `currency`.
- Feature: cannot change `currency_id` on update.
- Unit: `GoalBalance` ignores Savings legs in another currency when goal is PLN.
- Feature: transfer with goal fails when goal currency ≠ account currency (future-proof test with second currency in factory if needed, or mock).
- Feature: existing goals migration → PLN.

## Out of scope (unchanged)

- Multiple currencies in UI beyond seeded set
- Exchange rates and converted totals
- Per-user default currency
- Goal tied to a specific Savings account

# Accounts savings summary — design spec

**Status:** Approved in brainstorming (2026-06-11)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Reference UI:** `resources/js/pages/accounts/Index.vue`, `resources/js/components/accounts/AccountsSummaryCard.vue`

## Summary

Add a second metric to the accounts index summary card: total balance of **savings accounts in PLN only**. Display it as a new line below the existing “Suma sald (PLN)” total. No account count for savings. Frontend-only change — reuse the same client-side aggregation pattern already used for the overall PLN total.

## Decisions log

| Topic | Decision |
|-------|----------|
| Currency rule | **A:** PLN only — same as existing overall total |
| Placement | **A:** Second line in the same `AccountsSummaryCard`, below overall total |
| Savings account count | **No** — amount only |
| Scope (account types) | Savings (`type === 'savings'`) only; no separate checking breakdown |
| Implementation approach | **A:** Extend `AccountsSummaryCard.vue` client-side computed (no backend summary prop) |

## Current state

- `Index.vue` renders `AccountsSummaryCard` with the full `accounts` prop from Inertia.
- `AccountsSummaryCard.vue` filters `currency.code === 'PLN'`, parses `current_balance`, and shows one total plus PLN account count.
- `AccountResource` already exposes `type` (`checking` / `savings`) on each account.
- `AccountController::index()` requires no change.

## Target behavior

### Savings total line

- Label (i18n): e.g. `accounts.summary.savingsPln` — “Oszczędności (PLN)” / “Savings (PLN)”.
- Value: sum of `current_balance` for accounts where `type === 'savings'` **and** `currency.code === 'PLN'`.
- Formatting: same `Intl.NumberFormat('pl-PL')` + default currency symbol as the overall total.
- Invalid balances: same skip logic as overall total (contribute `0` to sum; existing `invalidSkipped` message still applies globally).

### Edge cases

| Scenario | Display |
|----------|---------|
| No savings accounts | `0,00 zł` (line always visible) |
| Savings only in non-PLN currency | `0,00 zł` |
| Mix of checking + savings in PLN | Overall total unchanged; savings line shows savings subset only |
| No PLN accounts at all | Overall `0,00 zł`; savings `0,00 zł`; existing empty hint unchanged |

### UI layout (card content)

```
Podsumowanie
Suma sald wszystkich kont w PLN.

Suma sald (PLN)
12 345,67 zł

Oszczędności (PLN)
8 000,00 zł

Liczba kont (PLN): 3
```

The footer row (`countPln`, `invalidSkipped`, `addToSeeSummary`) remains unchanged and still refers to **all** PLN accounts.

## Files

| File | Change |
|------|--------|
| `resources/js/components/accounts/AccountsSummaryCard.vue` | Add `type` to local `Account` type; filter savings + PLN; add `savingsTotalBalance` / `formattedSavingsTotal`; second metric block in template |
| `resources/js/locales/pl.json` | Add `accounts.summary.savingsPln` |
| `resources/js/locales/en.json` | Add `accounts.summary.savingsPln` |
| `resources/js/pages/accounts/Index.vue` | **No change** (already passes full accounts with `type`) |
| Backend (`AccountController`, `AccountResource`) | **No change** |

## Out of scope

- Per-currency savings breakdown (EUR, USD, etc.)
- Checking-only subtotal
- Backend-computed `summary` prop or new Action
- Savings account count
- Changes to card title/description copy (still describes overall PLN total)

## Verification

| Check | Method |
|-------|--------|
| Savings PLN sum correct | Manual: 2+ savings accounts in PLN; confirm line matches manual sum |
| Checking excluded | Manual: checking PLN balance not included in savings line |
| Non-PLN savings excluded | Manual: EUR savings account → savings line stays `0,00 zł` |
| No savings accounts | Manual: only checking accounts → savings line `0,00 zł` |
| Invalid balance handling | Manual: if applicable, confirm same behavior as overall total |

No new PHP/Pest tests — no API contract or backend logic change.

## Implementation notes (for plan)

1. Extend `Account` type in `AccountsSummaryCard.vue` with `type: string`.
2. Add `savingsAccountsInPln` computed: `accounts.filter(a => a.currency.code === 'PLN' && a.type === 'savings')`.
3. Reuse `parseAmount` and reduce pattern for `savingsTotalBalance` / `formattedSavingsTotal`.
4. Add second `<div>` block in `CardContent` between overall total and footer metadata.
5. Add i18n keys in `pl.json` and `en.json`.
6. Manual smoke test on `/accounts`.

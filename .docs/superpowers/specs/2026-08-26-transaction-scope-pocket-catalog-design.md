# Transaction scope and pocket catalog — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** Transaction assignment (XOR category vs pocket), transfer pocket movement, catalog IA, pocket delete without archive  
**Supersedes (partial):** pocket assignment rows in `.docs/prd.md` FR-P3, FR-P4; savings-account filter in pocket balance (FR-C5 / FR-P2); archive UX in FR-P1  
**Builds on:** `.docs/superpowers/specs/2026-06-04-transfer-category-decoupling-design.md` (P&L categories vs transfers); `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md` (`PageTabs` + `pageRegistry`)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Canonical requirements target:** `.docs/prd.md`

## Summary

A transaction has **exactly one label**, driven by a derived **scope** (not a stored column):

- **External** (income / expense / adjustment): required **category**. `pocket_id` always null.
- **Internal** (transfer pair): no category. Optional **pocket** plus an explicit **Odłożenie / Wypłata** (`pocket_movement`). Envelope math uses that intent, **once per `transfer_id`**, independent of account type.

Pockets stay a separate model (target, planning, currency). The sidebar merges **Kategorie** and **Kieszenie** into one nav item with two tabs. Archive and pocket list filters go away; delete always works by clearing `pocket_id` on linked transfers first.

## Problem

The product already distinguishes world-facing P&L from movements between the user’s own accounts, but the UI splits that across categories, pockets, and a Savings-only envelope formula. Users think in one story: set money aside for a purpose, later pull it back, then spend with a normal category. Today that is distorted by:

1. **Dual assignment (FR-P4):** an expense can carry both a category and a pocket, mixing “what I bought” with “what I saved for”.
2. **Savings-gated pockets (FR-P3):** ROR↔ROR cannot have a pocket; Savings transfers must. Cash wallets have no “savings cash”, so the rule cannot be the source of truth.
3. **Two catalog entries** (Kategorie + Kieszenie) for what the user treats as one classification place.
4. **Two transfer legs** that net to zero if both are summed — envelope totals must count the pair once, with an explicit direction.

## Decisions log

| Topic | Decision |
|-------|----------|
| Dual category + pocket on P&L | **Dropped (FR-P4).** XOR: external → category only; internal → pocket only (optional) |
| Scope on `transactions` | **Not a column.** Derived: `transfer_id` set → internal; else external |
| Category + Pocket tables | **Stay separate.** Catalog merge is navigation only |
| Pocket required on Savings transfer | **No.** Optional on every transfer, including Savings |
| Pocket on ROR↔ROR / cash | **Allowed** when the user assigns one |
| Envelope direction | Stored **`pocket_movement`**: `deposit` \| `withdrawal`. Required iff `pocket_id` set. Count **once per `transfer_id`** |
| Savings account type in balance | **Not used.** Intent is `pocket_movement`, not `AccountType::Savings` |
| Same-account virtual allocation (YNAB) | **Out of scope.** Envelope grows via internal transfers (or `initial_balance`) |
| “Zakres” in UI | **Not shown.** Create transaction vs create transfer *is* the scope |
| Catalog nav | **One sidebar item** (Kategorie), tabs across `/kategorie` and `/kieszenie` |
| Pocket list filters | **Removed.** No Aktywne / Zarchiwizowane / Wszystkie |
| Archive | **Removed from product.** Existing `is_archived = true` rows are unarchived (visible); user may delete |
| Delete pocket with history | **Always allowed:** null `pocket_id` + `pocket_movement` on those transfers, then delete the pocket |
| Post-import pocket | Editable on the **linked-transfer** edit modal; writes **both legs** |
| Pocket filter on transaction list | **Out of scope** |
| Rename “transfer” in UI | **Out of scope** |

## Mental model (UX copy, not schema)

Two kinds of movement:

1. **With the world** — salary, purchases, balance adjustment. Label: **category** (required).
2. **Between my accounts** — ROR↔savings, ROR↔ROR, cash↔ROR. Label: optional **pocket** + **Odłożenie** or **Wypłata**.

A pocket answers “what am I setting money aside for?”, not “what did I buy?”.

Canonical flow (also the explanation for a new user):

1. Set aside 200 zł for holidays → internal transfer, pocket Wakacje, **Odłożenie** → pocket balance +200.
2. Use that money → transfer back to the account you will pay from, same pocket, **Wypłata** → balance −200.
3. Pay in the shop → external expense, category Rozrywka. **No pocket.**

Step 3 is not linked to the envelope. The envelope already moved in step 2.

## Catalog IA

### Navigation

| Place | Text |
|-------|------|
| Sidebar | **Kategorie** (single item; active on `categories.*` and `pockets.*`) |
| Tab 1 | **Przychody i wydatki** → `categories.index` (`/kategorie`) |
| Tab 2 | **Kieszenie** → `pockets.index` (`/kieszenie`) |
| Page `h1` / node title | **Kategorie** on the categories index; **Kieszenie** on the pockets index |

Keep localized paths and named routes. New `pageTabGroup` (e.g. `catalog`) on both index nodes, same pattern as budget (two routes, one tab strip). Default tab: categories.

Remove `tabGroup: 'pockets'` (filter tabs). Query `?filter=` on `pockets.index` is ignored; do not 404 old bookmarks.

Pockets **create/edit** stay their own pages (`pockets.create` / `pockets.edit`), children of the pockets node. Remove the archive checkbox from edit. Category create/edit unchanged.

Do **not** call pockets “internal categories” in UI. They remain kieszenie (envelopes with target, planning, currency).

### Transaction forms

- **P&L modal** (create/edit income, expense, adjustment): category picker only. No pocket field. Reject `pocket_id` in the request.
- **Transfer create** (`transfers.create`, still a page): pocket optional. When a pocket is selected, show **Odłożenie | Wypłata** (required). Default **Odłożenie**. Convenience: if **exactly one** of the two accounts is `Savings`, preselect Odłożenie when that account is the destination, Wypłata when it is the source. Always overridable. Stored field is the source of truth, not account type.
- **Linked transfer edit** (existing transaction modal + unlink banner): hide category; show pocket + Odłożenie/Wypłata. Persist to **both legs**. Clearing the pocket nulls `pocket_id` and `pocket_movement` on both legs. Amount and accounts stay locked until unlink (unchanged).

Hint on the movement control (Polish): Odłożenie zwiększa saldo kieszeni. Wypłata zmniejsza — gdy ściągasz odłożone pieniądze, żeby je wydać.

### Transaction list

- P&L row: category badge (unchanged). Category filter unchanged (transfers already have `category_id` null).
- Transfer row: transfer indicator + pocket name when set. Do **not** show Odłożenie/Wypłata on the row (budget shows direction).
- Index summary still excludes internal transfers (`transfer_id` empty).

## Data model

### Scope (derived)

| Scope | Detection | `category_id` | `pocket_id` | `pocket_movement` |
|-------|-----------|---------------|-------------|-------------------|
| External | `transfer_id` empty | Required | Always null | Always null |
| Internal | `transfer_id` set | Always null | Optional; **same on both legs** | Null iff no pocket; else `deposit` or `withdrawal`; **same on both legs** |

API **422** when those invariants break (including P&L with a pocket, transfer with a category, movement without pocket, pocket without movement, mismatched legs, foreign pocket, pocket currency ≠ transfer currency).

`type = transfer` remains the pair type. Do not add `scope` to the schema.

### `transactions.pocket_movement`

Nullable string enum, PHP `PocketMovement`: `Deposit`, `Withdrawal`. Both legs of a pair store the same value.

Currency rule: pocket `currency_id` must equal the (single) currency of the two transfer accounts. Not tied to `AccountType`.

### Pockets catalog

- List **all** of the user’s pockets (no `is_archived` filter in queries used by index, pickers, or monthly budget).
- Remove archive from UI, requests, telemetry (`pocket_archived` / `pocket_unarchived`), and `pageRegistry` filter tabs.
- Migration: `UPDATE pockets SET is_archived = 0`. Then drop `is_archived` (column, casts, factory, tests) so the field does not linger.
- **Delete:** in one DB transaction, set `pocket_id = null` and `pocket_movement = null` on the user’s transfers with that pocket, then delete the pocket. No longer blocked by `hasLinkedTransactions()`.

### Envelope totals

Count **one amount per `transfer_id`** (`ABS` of either leg). Ignore account type.

```
saved     = sum of |amount| where pocket_movement = deposit
released  = sum of |amount| where pocket_movement = withdrawal
balance   = initial_balance + saved − released   (cumulative)
```

Monthly budget pocket row: same sums restricted to the month (`COALESCE(booked_at, date)`). Drop `linked_expenses` from `PocketTransactionMetrics` and any UI that consumed it.

`PocketBalance::cumulative` / `monthlyNetMap` and monthly summary “saved → expenses, released → income” use this formula.

Empty pockets section: when the user has **no pockets**, not “no active pockets”.

## Error handling

Polish field errors, 422:

- P&L missing category, or `pocket_id` / `pocket_movement` present
- Transfer with `category_id`
- Pocket without movement, or movement without pocket
- Legs of a pair disagree on pocket or movement
- Pocket currency ≠ transfer currency
- Pocket not owned by the user

Unlink (unchanged outcome, plus movement): restore `income`/`expense` from amount sign, fallback `category_id`, `pocket_id` and `pocket_movement` null on both legs.

Import commit: still requires category (FR-C7). Matcher link: `category_id` null; leave pocket empty (user assigns on edit).

## Migration (existing data)

Run once on deploy, in order:

1. **P&L dual assignment:** `UPDATE transactions SET pocket_id = NULL WHERE transfer_id IS NULL` (and `pocket_movement` null once the column exists).
2. **Add `pocket_movement`.** Backfill transfer pairs that still have `pocket_id`:
   - If any Savings leg of the pair has `amount > 0` → `deposit` on both legs.
   - Else if any Savings leg has `amount < 0` → `withdrawal` on both legs.
   - Else → `deposit` on both legs (should not occur under old FR-P3).
3. **Unarchive** all pockets, then **drop `is_archived`.**
4. Do **not** delete pockets or transfers.

## Backend changes (implementation hint)

| Area | Change |
|------|--------|
| `ValidatesPocketId` | Transfers: pocket **optional** always; drop `prohibited` on ROR↔ROR and `required` when Savings. Currency vs both accounts. P&L: **prohibit** `pocket_id` |
| `StoreTransactionRequest` / `UpdateTransactionRequest` | Prohibit pocket on P&L; on linked transfer allow pocket + movement and apply to both legs |
| `StoreTransferRequest` / `CreateTransfer` | Optional pocket; `pocket_movement` required_with pocket; persist on both legs |
| `UpdateTransaction` | Linked: sync `pocket_id` + `pocket_movement` to the other leg; still forbid amount/account edits |
| `UnlinkTransfer` | Also clear `pocket_movement` |
| `DeletePocket` (or destroy action) | Null FKs then delete; remove `hasLinkedTransactions` block |
| `PocketBalance`, `PocketTransactionMetrics` | Rewrite around `pocket_movement` + group by `transfer_id`; drop Savings filter and `linked_expenses` |
| `ListPockets` / budget list | No `active()` / `is_archived` filter |
| `pageRegistry` + `AppSidebar` | Catalog tab group; one sidebar href |
| Resources | Expose `pocket_movement` on transfer rows that need the edit modal |

## PRD delta

- Glossary: category = external P&L label only; pocket = optional label on an internal transfer with Odłożenie/Wypłata; drop “optional pocket on expense”.
- FR-T3: pocket optional; `pocket_movement` when pocket set; ROR↔ROR may have a pocket; reject category (unchanged).
- FR-C2: unchanged for P&L; still null on transfers. Add: P&L must not have `pocket_id`.
- FR-C5: pocket metrics from `pocket_movement`, not Savings legs; no linked expenses; all pockets listed.
- FR-P1: remove filters and archive; delete clears assignments then removes the row. Update AC 2 and 4.
- FR-P2 / FR-P5: balance formula without Savings filter; mention `pocket_movement`.
- FR-P3: replace “required when Savings / prohibited on ROR↔ROR” with optional pocket + movement on any transfer; currency match to transfer currency.
- FR-P4: **remove** (Should). XOR forbids pocket on expense.
- Screens: one sidebar item; pockets edit without archive; P&L form without pocket; transfer form with optional pocket + movement.
- Telemetry: drop `pocket_archived` / `pocket_unarchived` / `pocket_assigned_transaction`; keep `pocket_assigned_transfer` when a transfer is saved with a pocket.
- Out of scope: same-account earmark without a second account; ML categories (unchanged).

## Testing

Feature / unit, scoped to the domain under change:

- P&L store/update with `pocket_id` → 422; without category → 422.
- Transfer without pocket → 201; with pocket and movement → 201 both legs; with pocket and no movement → 422; with `category_id` → 422.
- Savings transfer **without** pocket → 201 (reverses FR-P3).
- ROR↔ROR **with** pocket + deposit → 201; pocket balance +amount once (not 2×, not 0).
- Cash↔ROR with withdrawal → released += amount; balance decreases.
- Smart default is UI-only; API accepts either movement regardless of account types.
- Linked edit updates **both** legs’ pocket and movement.
- Unlink: fallback categories, pocket and movement null.
- Matcher link: categories null, pockets untouched (null).
- Delete pocket with history → 200; those transfers have null pocket; pocket row gone.
- Pockets index: no filter tabs; archived fixtures appear after migration unarchive.
- Monthly budget: deposit/withdrawal in month; no `linked_expenses`; section hidden iff zero pockets.
- `PocketBalance` unit: two-leg pair counted once; `initial_balance` included.

## Out of scope

- Virtual split of one account’s balance across pockets without a transfer
- Stored `scope` column or a “zakres” dropdown
- Merging `categories` and `pockets` tables
- Pocket filter on `/transakcje`
- Turning transfer create into a modal (already a separate spec)
- Yearly budget pocket section (yearly has none today)
- Auto-assigning a pocket on import match
- Multi-currency envelope math beyond “pocket currency must match transfer currency”

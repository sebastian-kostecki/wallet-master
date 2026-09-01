# Deposits import detection — design spec

**Date:** 2026-09-01  
**Status:** Approved in brainstorming  
**Scope:** Hybrid auto-detection of term deposit capital movements during CSV import (BNP Paribas + mBank rules first; hook on all import adapters). Interest stays normal P&L. Candidate banner + filter on transactions index.  
**Depends on:** `.docs/superpowers/specs/2026-08-27-deposits-design.md` (FR-D1 manual deposits — implemented)  
**Supersedes (partially):** import sections of `.docs/superpowers/specs/2026-06-11-deposits-design.md`  
**Related:** `.docs/prd.md` §6.8; `App\Imports\Workflow\CommitImport`; `App\Imports\TransferMatcher`

## Goal

When a user imports a bank statement, **deposit capital rows** (open / close principal) should be recognized automatically where the bank signal is unambiguous, and surfaced as **candidates** when not. Principal must never distort budget P&L. Interest lines remain normal categorized income (manual `interest` assign still available).

## Decisions log

| Topic | Decision |
|-------|----------|
| Automation | **Hybrid (C):** auto when confident; otherwise candidate for user confirm |
| Banks (v1 rules) | **Hook on all adapters (C);** concrete rules only BNP + mBank in this iteration |
| Interest | **Normal P&L (A)** — no auto-link to deposit |
| Candidate UI | **Banner + filter (C)** — mirror transfer candidates UX |
| Candidate category | **`category_id = null` immediately (A)** — capital excluded from P&L until confirmed or rejected |
| Architecture | **Inline classification in `CommitImport`** before `ResolveCategoryForImportRow` (recommended over post-import matcher) |
| Close match key | `principal` + `currency_id` + `user_id` + `status = active`; not `source_account_id` |
| Ambiguous close | 0 or 2+ matching active deposits → candidate; user picks deposit on confirm |

## Approaches considered

1. **Inline classification in `CommitImport` (chosen)** — classify each row before category resolution; auto paths call dedicated Actions; candidates inserted with `category_id = null`; `TransferMatcher` runs after all rows. Pros: category never wrongly assigned; single pass. Cons: deposit rows skip bulk insert (acceptable — rare rows).

2. **Post-import `DepositMatcher` (like `TransferMatcher`)** — bulk insert everything first, second pass. Pros: smaller change to row loop. Cons: requires category rollback or pre-classification anyway; extra queries; conflicts with immediate `category_id = null` for candidates.

3. **Queued post-import job** — rejected: unnecessary complexity for rare rows.

## Current state

- Deposits v1 (2026-08-27) is implemented: manual open/close/assign, third tab on `/konta?kind=deposits`, `BudgetExclusion` for capital legs, dashboard KPI.
- Import pipeline: `CommitImport::buildInsertRow` → `ResolveCategoryForImportRow` → bulk insert → `TransferMatcher::matchAfterImport`.
- PRD FR-D1 explicitly excludes CSV auto-classification; this spec adds **FR-D2**.
- June 2026 spec documented BNP/mBank signals; never shipped. Legacy `deposit_match_*` columns were removed by forward-fix migration `2026_08_27_112355` — reintroduced here with v2 semantics.

## Target behavior

### Data flow

```
CSV row → Adapter.normalizeRow()
       → DepositRowClassifier (bank-specific rules via adapter hook)
            ├─ auto open     → CreateDepositFromImport
            ├─ auto close    → CloseDepositFromImport (DepositCloser match)
            ├─ candidate     → Transaction insert (category_id=null, deposit_match_status=manual)
            └─ none          → ResolveCategoryForImportRow → bulk insert
       → (after all rows) TransferMatcher
```

Deposit capital rows (auto or confirmed) are excluded from `TransferMatcher` via existing `BudgetExclusion`.

### Schema changes

#### `transactions` — new columns

| Column | Type | Notes |
|--------|------|-------|
| `deposit_match_status` | string(20), default `none` | `none` \| `manual` \| `auto` \| `rejected` — mirrors `transfer_match_status` |
| `deposit_candidate_kind` | string(32), nullable | `open` \| `close_principal` when `deposit_match_status = manual`; cleared on confirm/reject/auto |

Index: `(user_id, deposit_match_status)`.

No `deposit_candidate_deposit_id` — on ambiguous close, user selects deposit in confirm UI.

#### New enums

- `App\Enums\DepositMatchStatus` — same cases as `TransferMatchStatus`
- `App\Support\Deposits\DepositClassificationIntent` — `none`, `open`, `close_principal`
- `App\Support\Deposits\DepositClassificationConfidence` — `auto`, `candidate`

### Import row outcomes

| Outcome | `deposit_match_status` | `deposit_candidate_kind` | `category_id` | Deposit / movement |
|---------|------------------------|--------------------------|---------------|-------------------|
| Auto open | `auto` | `null` | `null` | `active` deposit + `open` movement |
| Auto close | `auto` | `null` | `null` | `closed` deposit + `close_principal` movement |
| Candidate open | `manual` | `open` | `null` | none until confirm |
| Candidate close | `manual` | `close_principal` | `null` | none until confirm |
| None (P&L / interest) | `none` | `null` | from category memory | none |
| Rejected | `rejected` | `null` | `null` | none — user edits transaction to assign category |

`deposit_movements` rows are created only on auto or confirm, not on candidate alone.

### Bank classification rules (v1)

| Bank | Signal column | Auto open | Close |
|------|---------------|-----------|-------|
| BNP Paribas | `Typ transakcji` (also used as description fallback today) | `OTWARCIE LOKATY` + negative amount | `SPŁATA DEPOZYTU` + positive amount → auto close if exactly one active deposit with matching `principal`; else candidate |
| mBank | `Kategoria` (raw row) | `Lokaty i konto oszcz.` + negative amount | same category + positive amount → same close logic |

All other banks: classifier returns `none` (adapter hook returns default; no rules until added per bank).

**Note:** mBank `Kategoria` is used **only** for deposit classification, not for `category_id` (unchanged FR-C7 behavior).

### Auto-close matching

`Support/Deposits/DepositCloser::findMatchingActiveDeposits(User, principal, currencyId)`:

- Query `deposits` where `user_id`, `currency_id`, `status = active`, `principal = abs(amount)`.
- **0 matches** → candidate close.
- **1 match** → auto close via `CloseDepositFromImport`.
- **2+ matches** → candidate close; user picks `deposit_id` on confirm.

Return account may differ from `source_account_id`.

### Domain layout (Variant A)

| Layer | Path |
|-------|------|
| Support | `Support/Deposits/DepositRowClassifier` — orchestrator |
| Support | `Support/Deposits/BnpDepositRules`, `Support/Deposits/MBankDepositRules` |
| Support | `Support/Deposits/DepositCloser` |
| Support | `Support/Deposits/DepositClassification` (value object) |
| Actions | `Actions/Deposits/CreateDepositFromImport` |
| Actions | `Actions/Deposits/CloseDepositFromImport` |
| Actions | `Actions/Deposits/ConfirmDepositCandidate` |
| Actions | `Actions/Deposits/RejectDepositCandidate` |
| Import | `CommitImport` — call classifier before category resolution |
| Adapters | `BankImportAdapter` hook; `AbstractBankImportAdapter` default `none`; BNP/mBank overrides |
| HTTP | `Http/Controllers/Deposits/DepositCandidateController` |
| Resources | `Http/Resources/Deposits/DepositCandidateResource` |
| Frontend | `components/deposits/DepositCandidatesBanner.vue` |
| Tests | `tests/Feature/Deposits/`, `tests/Feature/Imports/` |

Reuse existing `AssignTransactionToDeposit` logic internally where possible (confirm open/close delegates to same invariants).

### Confirm / reject

**Confirm** (`ConfirmDepositCandidate`):

- Requires `deposit_match_status = manual`.
- **Open:** amount < 0, bank account, no existing movement → create deposit + `open` movement (same rules as assign open); set `deposit_match_status = manual` (user confirmed from banner).
- **Close:** requires `deposit_id`; amount must equal chosen active deposit `principal`; create `close_principal` movement + close deposit.
- Request body for close: `{ deposit_id: int }`.

**Reject** (`RejectDepositCandidate`):

- Sets `deposit_match_status = rejected`, clears `deposit_candidate_kind`.
- `category_id` stays `null`; user must edit transaction and assign a P&L category manually (same semantics as transfer candidate reject).

Policies: `TransactionPolicy::confirmDepositCandidate`, `rejectDepositCandidate` — mirror transfer candidate checks.

### Routes

```
POST /lokaty/kandydaci/{transaction}/potwierdz   deposits.candidates.confirm
POST /lokaty/kandydaci/{transaction}/odrzuc      deposits.candidates.reject
```

Named routes follow existing localized `route_path` pattern for deposits.

### UI — transactions index

**Banner:** `DepositCandidatesBanner.vue` below transfer candidates banner when `pending_deposit_candidates.length > 0`.

Candidate card fields: transaction id, kind (`open` / `close_principal`), amount, date, description, account; for close — dropdown of `matching_deposits` (active, same principal + currency).

Actions: Confirm / Reject with partial Inertia reload of `pending_deposit_candidates`.

**Filter:** query param `deposit_candidates=1` on `/transakcje` → scope `deposit_match_status = manual`. Chip/link **„Kandydaci lokaty (N)”** where N = pending count. Works alongside existing filters.

**Row badge:** „Kandydat lokaty” for pending candidates; existing „Lokata” badge after linked movement.

`ListTransactions` gains `getPendingDepositCandidates()`; `Transaction` model gains `scopePendingDepositCandidate()`.

### Telemetry

| Event | When |
|-------|------|
| `deposit_import_open_auto` | auto open during import |
| `deposit_import_close_auto` | auto close during import |
| `deposit_import_open_candidate` | candidate open inserted |
| `deposit_import_close_candidate` | candidate close inserted (includes unmatched / ambiguous) |
| `deposit_import_open_confirmed` | user confirmed open candidate |
| `deposit_import_close_confirmed` | user confirmed close candidate |
| `deposit_import_candidate_rejected` | user rejected candidate |

Add FR-D2 events to PRD §3.4 when implementing.

### Testing

| Area | Coverage |
|------|----------|
| BNP auto open | Feature + `tests/Fixtures/import/bnp-deposit-open.csv` |
| BNP auto close | Feature + fixture; pre-seed one active deposit |
| BNP ambiguous close | Two active deposits same principal → candidate, no wrong close |
| mBank category rules | Feature + fixture rows with `Kategoria` |
| Confirm / reject | `DepositCandidateConfirmRejectTest` |
| Budget / summary | Candidate and auto capital excluded; interest P&L unchanged |
| TransferMatcher | Auto open row not offered as transfer candidate |
| Index banner + filter | Inertia props + filtered list |
| Default bank hook | Bank without rules → unchanged P&L import |

### PRD / checklist updates (implementation PR)

- Add **FR-D2 — Import lokat (BNP/mBank)** in §6.8.
- Update FR-D1 note: CSV auto-classification deferred in v1 → **available for BNP/mBank** via FR-D2.
- `.docs/checklist.md`: new subsection under import / deposits.

## Out of scope

- Interest auto-link during import
- Description-keyword heuristics for unsupported banks
- Combined principal + interest on one bank line
- Import UI counters for deposit rows (telemetry only in v1)
- Top-ups, partial close, deposit delete
- Auto-fill `matures_at` from bank data

## Self-review checklist

- [x] No TBD placeholders blocking implementation
- [x] Consistent with Variant A (`Deposits`, Actions, no Resources in Actions)
- [x] Budget rules: capital null category; interest unchanged P&L path
- [x] Hybrid auto/candidate behavior explicit per bank signal
- [x] Close ambiguity handled (0 / 1 / 2+ matches)
- [x] Reuses transfer candidate UX patterns
- [x] Single implementation-plan scope
- [x] Extends August v1 without breaking manual assign flows

## Implementation notes (for plan)

1. Migration: `deposit_match_status`, `deposit_candidate_kind` on `transactions`.
2. Enums + model casts + scopes on `Transaction`.
3. `DepositRowClassifier` + BNP/mBank rules + adapter hook on `BankImportAdapter`.
4. `CreateDepositFromImport`, `CloseDepositFromImport`, `DepositCloser`.
5. Integrate into `CommitImport::buildInsertRow` (before category resolution); adjust balance accounting for non-bulk deposit rows.
6. `ConfirmDepositCandidate`, `RejectDepositCandidate`, routes, policies, controller.
7. `ListTransactions` pending candidates + filter; `DepositCandidateResource`; Vue banner + filter chip + i18n.
8. Fixtures + feature tests; PRD + checklist; Pint + Sail tests.

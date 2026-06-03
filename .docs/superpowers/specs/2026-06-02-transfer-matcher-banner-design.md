# Transfer Matcher + Candidates Banner — Design Spec

**Date:** 2026-06-02  
**Status:** Approved (brainstorming 2026-06-02)  
**Related:** `.docs/checklist.md` §6.6, `.docs/improvement-plan.md` §4, PRD FR-I6, FR-T3  
**Supersedes:** improvement-plan §4 step 6 (separate „Możliwe transfery" page + `GET /transfers/candidates` Inertia route)

## Problem

After importing statements from two banks, opposite transactions (e.g. mBank −200 PLN and BNP +200 PLN) remain as separate income/expense rows. Users must recognize and link them manually. The product should auto-link obvious internal transfers and surface uncertain pairs for a quick confirm/reject decision **without leaving the transactions list**.

## Goals

1. After each successful import commit, run a transfer matcher on newly imported transactions.
2. Auto-link high-confidence pairs (`probable`) with shared `transfer_id` and `type = transfer` without changing balances.
3. Mark uncertain pairs (`manual`) with bidirectional `transfer_candidate_for_id` for user review.
4. Show pending pairs in an expandable banner on `transactions/Index.vue` (same placement pattern as `ImportFailedRowsBanner`).
5. Allow confirm / reject per pair via POST endpoints; support unlink for linked transfers (backend + tests; Edit UI deferred).

## Non-Goals

- Separate navigation item or Inertia page „Możliwe transfery".
- Sidebar badge for pending transfer count.
- `GET /transfers/candidates` as a full-page Inertia response.
- Dismiss-without-decision (unlike import failed rows).
- Re-import / retry of matcher for historical data outside import commit.
- Multi-currency transfer matching (MVP: skip when `currency_id` differs).
- ~~UI for „Rozłącz transfer" on Edit~~ — done in `.docs/superpowers/plans/2026-06-03-transfer-edit-unlink.md` (2026-06-03).
- ~~Blocking edit of `amount` on linked transfers~~ — done (same plan).

## Decisions (from brainstorming)

| Topic | Decision |
|---|---|
| UX surface | Banner-only on transactions index (option A) |
| Data to Vue | Inertia prop `pending_transfer_candidates` via `ListTransactions` getter |
| Pair identity | One banner row per pair; query `id < transfer_candidate_for_id` |
| Ambiguous match (>1 candidate) | No auto-link; manual link to best candidate (min date delta, then min id); telemetry `transfer_match_skipped_ambiguous` |
| Matcher failure | Log warning; do not fail import commit |
| Manual UI transfer vs import link | UI `CreateTransfer` keeps `income`/`expense` + `transfer_id`; import auto-link uses `type = transfer` per PRD |
| Banner styling | Blue/violet informational (not amber error) |

---

## Data Model

### Migration: `add_transfer_matching_to_transactions_table`

| Column | Type | Notes |
|---|---|---|
| `transfer_match_status` | `VARCHAR(20)` NOT NULL DEFAULT `'none'` | See enum |
| `transfer_candidate_for_id` | `BIGINT UNSIGNED NULL` | FK → `transactions.id`, `nullOnDelete()` |

**Indexes:**

- `(user_id, transfer_match_status)` — banner query

**Backfill:** existing rows remain `transfer_match_status = 'none'`, `transfer_candidate_for_id = NULL`.

### Enum: `TransferMatchStatus`

| Value | Meaning |
|---|---|
| `none` | Default; no matcher involvement |
| `auto` | Matcher auto-linked with `transfer_id` |
| `manual` | Pending user confirm, or user-confirmed link (still `manual` after confirm per checklist) |
| `rejected` | User rejected or unlinked; never proposed again |

### Model `Transaction` updates

- Add to `$fillable`: `transfer_match_status`, `transfer_candidate_for_id`
- Cast `transfer_match_status` → `TransferMatchStatus::class`
- Relation `transferCandidate(): BelongsTo<Transaction, self>`
- Scope `scopePendingTransferCandidate(Builder $query): void` — `manual`, `transfer_candidate_for_id` not null, `id < transfer_candidate_for_id`

---

## Transfer Matcher

### Class

`App\Imports\TransferMatcher` — `matchAfterImport(Import $import): TransferMatcherResult` (result holds counts: `autoLinked`, `manualLinked`, `ambiguousSkipped` for optional import summary).

### Invocation (`CommitImport::handle`)

After all chunk flushes, inside the **final** `DB::transaction`, **before** `current_balance` update and `status = committed`:

```
lock account + import
TransferMatcher::matchAfterImport($importFresh)
update balance + counters + committed
```

Wrap matcher in try/catch; on exception log warning and continue commit.

### Input set

`Transaction::query()->where('import_id', $import->id)->orderBy('id')`

### Candidate pool (for each imported transaction `T`)

Candidates `C` must satisfy:

- `C.user_id = T.user_id`
- `C.account_id != T.account_id`
- `C.transfer_id IS NULL`
- `C.transfer_match_status != rejected`
- `C.transfer_candidate_for_id IS NULL`
- `T.transfer_match_status` is `none` (not already matched in this import pass)
- Same currency: `accounts.currency_id` equal for both legs
- Opposite signs: `bccomp(T.amount, '0', 2) * bccomp(C.amount, '0', 2) < 0`
- Same absolute amount: `bccomp(abs(T.amount), abs(C.amount), 2) === 0`
- Date window: `abs(days(T.date, C.date)) <= 3`

### Description tokens (`config/imports.php`)

```php
'transfer_tokens' => [
    'przelew własny',
    'przelew wewn',
    'transfer',
    'własny',
    'between accounts',
],
```

Token check: case-insensitive substring in `description` OR `raw_statement_description` (prefer both concatenated normalized).

**Probable** = exactly one candidate AND token present in **either** leg’s description fields.

**Manual** = exactly one candidate AND no token in either leg.

**Ambiguous** = two or more candidates → pick best by smallest date delta, then smallest `C.id`; set bidirectional manual link; emit `transfer_match_skipped_ambiguous` with `transaction_id`, `candidate_count`.

### Auto-link actions

- `transfer_id = (string) Str::uuid()` (shared)
- Both `type = TransactionType::Transfer`
- Both `transfer_match_status = auto`
- Clear `transfer_candidate_for_id` on both
- **No** balance recalculation
- Telemetry: `transfer_auto_linked` (`transfer_id`, both `transaction_id`s)

### Manual-link actions

- `transfer_candidate_for_id` cross-set (T→C, C→T)
- Both `transfer_match_status = manual`
- No `transfer_id`
- Telemetry: none required on propose (optional debug log)

### Skip rules

- `T` or `C` already has `transfer_id` (UI transfer)
- Either leg `rejected`
- Either leg already has a different pending candidate
- Currency mismatch

### Processing order

Process imported transactions by ascending `id`. After linking T↔C, both are excluded from further matching in the same run.

---

## User Actions (HTTP)

### Routes (`routes/transfers.php`)

| Method | Route | Name | Handler |
|---|---|---|---|
| POST | `transfers/candidates/{transaction}/confirm` | `transfers.candidates.confirm` | `TransferCandidateController@confirm` |
| POST | `transfers/candidates/{transaction}/reject` | `transfers.candidates.reject` | `TransferCandidateController@reject` |
| POST | `transfers/{transferId}/unlink` | `transfers.unlink` | `TransferController@unlink` |

No `GET` Inertia page for candidates.

### `ConfirmTransferCandidate` (`App\Actions\Transfers\ConfirmTransferCandidate`)

 Preconditions:

- Caller owns both transactions
- Anchor `transfer_match_status = manual`
- Partner `id = anchor.transfer_candidate_for_id`
- Partner points back to anchor
- Both `transfer_id` null

 Effects (single DB transaction, `lockForUpdate` both rows):

- Shared `transfer_id = Str::uuid()`
- Both `type = transfer`
- Both `transfer_match_status = manual`
- Both `transfer_candidate_for_id = null`
- Telemetry: `transfer_manually_linked`

 Redirect: back with toast `transfers.toast.candidate_confirmed`.

### `RejectTransferCandidate` (`App\Actions\Transfers\RejectTransferCandidate`)

- Both legs `transfer_match_status = rejected`
- Clear `transfer_candidate_for_id` on both
- Redirect back, toast `transfers.toast.candidate_rejected`

### `UnlinkTransfer` (`App\Actions\Transfers\UnlinkTransfer`)

- Load both rows by `transfer_id` + `user_id`; expect exactly 2
- Clear `transfer_id`
- Each `type = TransactionType::fromAmount(amount)`
- Both `transfer_match_status = rejected`
- Telemetry: `transfer_unlinked`
- **No balance change**

### Authorization

Extend `TransactionPolicy` (or dedicated policy methods):

- `confirmTransferCandidate(User, Transaction)`
- `rejectTransferCandidate(User, Transaction)`

Require ownership and valid manual-pending state.

---

## Read Path (Transactions Index)

### `ListTransactions`

Private step `handlePendingTransferCandidates(TransactionIndexRequest $request)`:

```php
Transaction::query()
    ->with(['account:id,name,bank', 'transferCandidate.account:id,name,bank', 'transferCandidate.currency:id,symbol,precision', 'currency:id,symbol,precision'])
    ->whereBelongsTo($user)
    ->pendingTransferCandidate()
    ->when($accountId, fn ($q) => $q->where(function ($q) use ($accountId) {
        $q->where('account_id', $accountId)
          ->orWhereHas('transferCandidate', fn ($q2) => $q2->where('account_id', $accountId));
    }))
    ->orderByDesc('date')
    ->orderBy('id')
    ->get();
```

Map each anchor + `transferCandidate` to pair DTO in controller via `TransferCandidatePairResource`.

### `TransactionController::index`

Add prop:

```php
'pending_transfer_candidates' => TransferCandidatePairResource::collection(
    $listTransactions->getPendingTransferCandidates(),
)->resolve(),
```

### Resource: `TransferCandidatePairResource`

Resolved shape per pair:

| Field | Source |
|---|---|
| `anchor_transaction_id` | Lower `id` of pair (anchor row) |
| `amount` | Absolute amount (string, 2 decimals) |
| `date_delta_days` | `abs(days(from.date, to.date))` |
| `from_account` | Account of negative leg |
| `to_account` | Account of positive leg |
| `from_transaction` | Negative leg: id, date, booked_at, amount, description |
| `to_transaction` | Positive leg: same fields |

---

## Frontend

### Component: `TransferCandidatesBanner.vue`

Location: `resources/js/components/transfers/TransferCandidatesBanner.vue`

Pattern: `ImportFailedRowsBanner.vue` — collapsible header, expandable table.

| Aspect | Choice |
|---|---|
| Color | `border-sky-500/40 bg-sky-50/40` (or violet variant) |
| Icon | `ArrowRightLeft` |
| Props | `pairs`, `accounts`, `accountFilterId?` |
| Actions | POST confirm/reject per row using `anchor_transaction_id` |
| Reload | `only: ['pending_transfer_candidates']`, `preserveScroll` |
| Grouping | By `from_account` when no account filter (like failed rows) |

No „dismiss all".

### `transactions/Index.vue`

Below `ImportFailedRowsBanner`:

```vue
<TransferCandidatesBanner
  v-if="(pending_transfer_candidates ?? []).length > 0"
  :pairs="pending_transfer_candidates ?? []"
  :accounts="accounts"
  :account-filter-id="filters.account_id"
/>
```

### i18n (Polish)

- `transfers.candidates.banner.title` — „{count} możliwych transferów do potwierdzenia"
- `transfers.candidates.banner.subtitle` — krótki opis heurystyki
- `transfers.candidates.table.from` / `.to` / `.amount` / `.dates` / `.descriptions`
- `transfers.candidates.actions.confirm` — „Potwierdź transfer"
- `transfers.candidates.actions.reject` — „To nie transfer"
- `transfers.toast.candidate_confirmed` / `candidate_rejected`

### Import modal (optional, same PR)

When `ImportStatusUpdated` payload includes `transfer_candidates_pending > 0`, show hint in result step pointing to transactions banner. Defer if timeboxed.

---

## Logging & Telemetry

| Event | When |
|---|---|
| `transfer_auto_linked` | Auto-link applied |
| `transfer_manually_linked` | User confirmed candidate |
| `transfer_unlinked` | Unlink endpoint |
| `transfer_match_skipped_ambiguous` | >1 candidate, best manual only |

Channel: `Log::channel('telemetry')` JSON line (same pattern as import telemetry).

---

## Edge Cases

| Case | Behavior |
|---|---|
| Import with 0 new transactions | Matcher no-op |
| Single-account import | Matcher runs; may link to older txs on other accounts |
| Both legs in same import file | Impossible (one `account_id` per import) |
| User confirms pair | Banner row disappears (`transfer_candidate_for_id` cleared) |
| User rejects pair | Hidden from banner; never re-proposed |
| Filter list by account A | Show pair if leg A or leg B on account A |
| Transfer created via UI | Has `transfer_id`; matcher skips |
| Matcher throws | Import still commits; warning log |
| Deleted account on leg | Policy/confirm aborts 403 |
| `CreateTransfer` types | Stays income/expense; only import matcher sets `type=transfer` for auto |

---

## Testing

### Feature — Imports (`tests/Feature/Imports/`)

1. **TransferMatcherAutoTest** — two accounts, token descriptions, shared `transfer_id`, `type=transfer`, `auto`, balances unchanged.
2. **TransferMatcherManualTest** — no token → `manual`, cross `transfer_candidate_for_id`, no `transfer_id`.
3. **TransferMatcherRejectsAmbiguousTest** — two candidates → no `auto`; best pair `manual`; telemetry fake assert.

### Feature — Transfers (`tests/Feature/Transfers/`)

4. **TransferCandidateConfirmRejectTest** — confirm sets `transfer_id`; reject sets `rejected`; 403 for other user.
5. **TransfersUnlinkTest** — restores income/expense types, `rejected`, clears `transfer_id`.

### Feature — Transactions (`tests/Feature/Transactions/`)

6. **TransactionIndexTransferCandidatesTest** — prop present/absent; account filter scopes pairs.

Run: `php artisan test --compact --filter=Transfer`

---

## Security

- All queries scoped by `user_id` / `whereBelongsTo($user)`.
- Confirm/reject require reciprocal `transfer_candidate_for_id` (prevents IDOR on arbitrary tx id).
- Rate limit: inherit auth middleware + future `throttle:api` (60/min).

---

## Documentation updates (post-implementation)

- `.docs/checklist.md` §6.6 — replace page/menu items with banner; check off completed sub-items.
- `.docs/prd.md` §8 — note: MVP candidate review = transactions index banner (nav item deferred).

---

## Acceptance Criteria

1. Given import mBank −200 „przelew własny" and prior BNP +200 within 3 days  
   When second import commits  
   Then both transactions share `transfer_id`, `type=transfer`, `transfer_match_status=auto`, balances unchanged.

2. Given matching amounts/dates but no transfer token  
   When import commits  
   Then both have `manual` status and mutual `transfer_candidate_for_id`; banner shows one pair.

3. Given user clicks „Potwierdź transfer" on banner  
   When POST succeeds  
   Then pair has `transfer_id`, `type=transfer`, banner no longer lists them.

4. Given user clicks „To nie transfer"  
   When POST succeeds  
   Then both `rejected`; banner empty for that pair; matcher never re-links them.

5. Given >1 candidate for imported row  
   When matcher runs  
   Then no `auto` link; telemetry `transfer_match_skipped_ambiguous`; at most one manual pair to best candidate.

6. Given no pending candidates  
   When user opens transactions index  
   Then no transfer candidates banner (failed-rows banner independent).

---

## Spec Self-Review (2026-06-02)

- [x] No TBD / TODO placeholders
- [x] Consistent with Variant A architecture (Actions, Resources, ListTransactions getter)
- [x] Banner-only UX aligned with user decision A; old page route explicitly removed
- [x] Ambiguous-match rule explicit (best candidate + telemetry)
- [x] Scope fits single implementation plan (matcher + banner + 3 POST actions)
- [x] Unlink UI deferred but endpoint in scope — stated in Non-Goals and Actions

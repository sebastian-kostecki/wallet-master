# Transfer Matcher + Candidates Banner — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete checklist §6.6 — match internal transfers after import, show pending pairs on transactions index banner, confirm/reject via POST (no separate page).

**Architecture:** DB columns + `TransferMatcher` in import commit final transaction; read via `ListTransactions` getter + `TransferCandidatePairResource`; Vue banner mirrors `ImportFailedRowsBanner`; write actions in `App\Actions\Transfers/`.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Tailwind 3.

**Spec:** `.docs/superpowers/specs/2026-06-02-transfer-matcher-banner-design.md`

---

## File map

| Action | Path |
|---|---|
| Create | `database/migrations/..._add_transfer_matching_to_transactions_table.php` |
| Create | `app/Enums/TransferMatchStatus.php` |
| Create | `app/Imports/TransferMatcher.php` |
| Create | `app/Imports/TransferMatcherResult.php` (optional small DTO) |
| Create | `app/Actions/Transfers/ConfirmTransferCandidate.php` |
| Create | `app/Actions/Transfers/RejectTransferCandidate.php` |
| Create | `app/Actions/Transfers/UnlinkTransfer.php` |
| Create | `app/Http/Controllers/Transfers/TransferCandidateController.php` |
| Create | `app/Http/Resources/Transfers/TransferCandidatePairResource.php` |
| Create | `resources/js/components/transfers/TransferCandidatesBanner.vue` |
| Create | `tests/Feature/Imports/TransferMatcherAutoTest.php` |
| Create | `tests/Feature/Imports/TransferMatcherManualTest.php` |
| Create | `tests/Feature/Imports/TransferMatcherRejectsAmbiguousTest.php` |
| Create | `tests/Feature/Transfers/TransferCandidateConfirmRejectTest.php` |
| Create | `tests/Feature/Transfers/TransfersUnlinkTest.php` |
| Create | `tests/Feature/Transactions/TransactionIndexTransferCandidatesTest.php` |
| Modify | `app/Models/Transaction.php` |
| Modify | `config/imports.php` |
| Modify | `app/Imports/Workflow/CommitImport.php` |
| Modify | `app/Actions/Transactions/ListTransactions.php` |
| Modify | `app/Http/Controllers/Transactions/TransactionController.php` |
| Modify | `app/Policies/TransactionPolicy.php` |
| Modify | `app/Http/Controllers/Transfers/TransferController.php` (unlink action) |
| Modify | `routes/transfers.php` |
| Modify | `resources/js/pages/transactions/Index.vue` |
| Modify | `resources/js/locales/pl.json` |
| Modify | `.docs/checklist.md` §6.6 |
| Modify | `.docs/prd.md` §8 (one-line MVP UX note) |

---

### Task 1: Schema + enum

**Files:**
- Create: migration `add_transfer_matching_to_transactions_table`
- Create: `app/Enums/TransferMatchStatus.php`
- Modify: `app/Models/Transaction.php`

- [ ] **Step 1:** Write migration with columns, FK, index; default `none`.
- [ ] **Step 2:** Add enum with cases `None`, `Auto`, `Manual`, `Rejected`.
- [ ] **Step 3:** Update `Transaction` — fillable, cast, `transferCandidate()` relation, `scopePendingTransferCandidate`.
- [ ] **Step 4:** Run `./vendor/bin/sail artisan migrate`.
- [ ] **Step 5:** `vendor/bin/pint --dirty --format agent`.

---

### Task 2: Config tokens

**Files:**
- Modify: `config/imports.php`

- [ ] **Step 1:** Add `transfer_tokens` array per spec.
- [ ] **Step 2:** No test required (covered by matcher tests).

---

### Task 3: TransferMatcher (TDD)

**Files:**
- Create: `app/Imports/TransferMatcher.php`
- Create: `tests/Feature/Imports/TransferMatcherAutoTest.php`
- Create: `tests/Feature/Imports/TransferMatcherManualTest.php`
- Create: `tests/Feature/Imports/TransferMatcherRejectsAmbiguousTest.php`

- [ ] **Step 1:** Write `TransferMatcherAutoTest` — two users’ accounts, pre-seed opposite tx on account B, import CSV on account A with „przelew własny", run `CommitImport` or call matcher directly; assert `transfer_id` shared, `type=transfer`, `auto`, balances unchanged.
- [ ] **Step 2:** Run test — fail.
- [ ] **Step 3:** Implement `TransferMatcher::matchAfterImport` — candidate query, token helper, auto branch.
- [ ] **Step 4:** Green auto test.
- [ ] **Step 5:** Write `TransferMatcherManualTest` — no token → `manual` + cross `transfer_candidate_for_id`, no `transfer_id`.
- [ ] **Step 6:** Implement manual branch; green.
- [ ] **Step 7:** Write `TransferMatcherRejectsAmbiguousTest` — two eligible candidates, assert no `auto`, one manual pair to closest date, `Log::fake` telemetry `transfer_match_skipped_ambiguous`.
- [ ] **Step 8:** Implement ambiguous branch; green.
- [ ] **Step 9:** Wire into `CommitImport` final transaction (try/catch); inject matcher via constructor or `app()`.
- [ ] **Step 10:** `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 11:** `./vendor/bin/sail artisan test --compact --filter=TransferMatcher`.

---

### Task 4: Confirm / reject actions (TDD)

**Files:**
- Create: `app/Actions/Transfers/ConfirmTransferCandidate.php`
- Create: `app/Actions/Transfers/RejectTransferCandidate.php`
- Create: `app/Http/Controllers/Transfers/TransferCandidateController.php`
- Modify: `app/Policies/TransactionPolicy.php`
- Modify: `routes/transfers.php`
- Create: `tests/Feature/Transfers/TransferCandidateConfirmRejectTest.php`

- [ ] **Step 1:** Add policy methods `confirmTransferCandidate`, `rejectTransferCandidate`.
- [ ] **Step 2:** Write feature test — seed manual pair, POST confirm, assert `transfer_id`, cleared candidates; POST reject on new pair, assert `rejected`; other user 403.
- [ ] **Step 3:** Implement actions + controller `confirm`/`reject` redirect back with toast keys.
- [ ] **Step 4:** Register routes in `routes/transfers.php`.
- [ ] **Step 5:** Green test; pint.

---

### Task 5: Unlink action (TDD)

**Files:**
- Create: `app/Actions/Transfers/UnlinkTransfer.php`
- Modify: `app/Http/Controllers/Transfers/TransferController.php`
- Modify: `routes/transfers.php`
- Create: `tests/Feature/Transfers/TransfersUnlinkTest.php`

- [ ] **Step 1:** Test — auto-linked or confirmed pair, POST `transfers/{transferId}/unlink`, types restored via `fromAmount`, `rejected`, `transfer_id` null.
- [ ] **Step 2:** Implement `UnlinkTransfer` + controller method.
- [ ] **Step 3:** Green; pint.

---

### Task 6: Index read path (TDD)

**Files:**
- Create: `app/Http/Resources/Transfers/TransferCandidatePairResource.php`
- Modify: `app/Actions/Transactions/ListTransactions.php`
- Modify: `app/Http/Controllers/Transactions/TransactionController.php`
- Create: `tests/Feature/Transactions/TransactionIndexTransferCandidatesTest.php`

- [ ] **Step 1:** Test — manual pair visible in `pending_transfer_candidates` on GET `transactions.index`; absent after confirm; filtered by `account_id` when set.
- [ ] **Step 2:** Add `handlePendingTransferCandidates` + getter in `ListTransactions`.
- [ ] **Step 3:** Build `TransferCandidatePairResource` (from anchor + loaded `transferCandidate`).
- [ ] **Step 4:** Pass prop in `TransactionController::index`.
- [ ] **Step 5:** Green; pint.

---

### Task 7: Vue banner + i18n

**Files:**
- Create: `resources/js/components/transfers/TransferCandidatesBanner.vue`
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/locales/pl.json`

- [ ] **Step 1:** Add Polish i18n keys from spec.
- [ ] **Step 2:** Create banner component (collapsible, table, confirm/reject POST, `only: ['pending_transfer_candidates']`).
- [ ] **Step 3:** Wire into `Index.vue` below import failed banner; extend page props type.
- [ ] **Step 4:** `npm run lint` (if configured) on touched Vue files.

---

### Task 8: Docs + full verification

**Files:**
- Modify: `.docs/checklist.md` §6.6
- Modify: `.docs/prd.md` §8

- [ ] **Step 1:** Check off completed §6.6 items; replace page/menu bullet with banner wording.
- [ ] **Step 2:** PRD §8 note: candidate review on transactions index.
- [ ] **Step 3:** `./vendor/bin/sail artisan test --compact --filter=Transfer`
- [ ] **Step 4:** `vendor/bin/pint --dirty --format agent`
- [ ] **Step 5:** `./vendor/bin/phpstan analyse` (if no new errors beyond baseline)

---

## Notes for implementer

- Reuse `CommitImport` test helpers from `CommitImportFailedRowsTest` for CSV fixtures.
- Amount signs: expense negative, income positive — same as existing import tests.
- `CreateTransfer` unchanged; do not set `transfer_match_status` on UI transfers (stays `none`).
- `DeleteTransaction` already deletes by `transfer_id` — regression-test if time permits.
- Branch: `improvement/transactions` (current).

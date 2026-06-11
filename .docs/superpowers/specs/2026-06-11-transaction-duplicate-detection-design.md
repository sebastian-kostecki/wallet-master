# Transaction duplicate detection command

**Date:** 2026-06-11  
**Status:** Approved  
**Scope:** `transactions:detect-duplicates` Artisan command, `Support/Transactions/TransactionDuplicateFinder`, tests

## Problem

The wallet allows intentional manual duplicates (`manualDedupeHash()` with UUID suffix). Import deduplication uses immutable `import_fingerprint`, but historical data or edge cases can still leave logically identical transactions in the database (same date, amount, and description) across accounts and users.

There is no operational tool to audit or clean up such rows. The duplicate-check UI was withdrawn from MVP; an Artisan command fills this gap for admin/ops use.

## Goals

- Detect globally (entire `transactions` table) groups of logically identical transactions.
- Report duplicate groups with enough context to act (IDs, account, user, import).
- Optionally delete redundant rows with `--delete-duplicates`, keeping the oldest row (`MIN(id)`) per group.
- Support `--dry-run` for safe preview before deletion.
- Reuse existing `DeleteTransaction` so account balances and transfer rules stay correct.

## Non-goals

- Per-account or per-user scoping flags (global only for v1).
- Configurable “keep” strategy (always `MIN(id)`).
- UI for duplicate management.
- Detecting import-fingerprint integrity violations as a separate mode (out of scope).
- Merging/editing duplicates (delete only).

## Decisions

| Topic | Choice |
|-------|--------|
| Scope | Global — all rows in `transactions` |
| Duplicate key | `date` (Y-m-d) + `amount` (2 decimal places) + `normalized_description` |
| Description source | Use `normalized_description` column; if empty, compute via `TransactionDedupe::normalizeDescription(description)` |
| Manual vs import | Treated equally |
| Canonical row | `MIN(id)` in each group |
| Default mode | Report only |
| Delete | Opt-in `--delete-duplicates`; preview with `--dry-run` |
| Exit code | `0` when no duplicates; `1` when any duplicate group exists |
| Deletion path | `DeleteTransaction::handle()` per candidate row |
| Transfer-linked rows | Skip deletion + warn (avoid breaking transfer pairs) |
| Soft-deleted account | Skip deletion + warn |

## Architecture

### `TransactionDuplicateFinder` (Support)

Pure, stateless class in `app/Support/Transactions/TransactionDuplicateFinder.php`.

Responsibilities:

1. Query or aggregate transactions by logical duplicate key.
2. Return groups with `keep_id`, `duplicate_ids`, and metadata for reporting.

Return shape (conceptual):

```php
[
    [
        'key' => [
            'date' => '2026-04-20',
            'amount' => '-49.99',
            'description' => 'biedronka',
        ],
        'keep_id' => 102,
        'duplicate_ids' => [458, 891],
        'transactions' => [
            ['id' => 102, 'account_id' => 3, 'user_id' => 1, 'import_id' => 12, 'transfer_id' => null],
            // ...
        ],
    ],
]
```

Implementation notes:

- Load candidate rows in chunks, compute a canonical `logical_description` per row (`normalized_description` when non-empty, otherwise `TransactionDedupe::normalizeDescription(description)`), then group in PHP by `(date, amount, logical_description)`. This avoids SQL `GROUP BY` missing rows where `normalized_description` was never backfilled but `description` normalizes to the same value.
- Amount in the key uses `TransactionDedupe::amountToDecimalString()`.
- Amount comparison uses the same decimal string format as `TransactionDedupe::amountToDecimalString()`.

### `DetectDuplicates` (Console command)

Location: `app/Console/Commands/Transactions/DetectDuplicates.php`

Signature:

```
transactions:detect-duplicates
    {--delete-duplicates : Delete duplicate rows, keeping the oldest (MIN id) per group}
    {--dry-run : With --delete-duplicates, list deletions without executing them}
```

Flow:

1. Call `TransactionDuplicateFinder` to load all duplicate groups.
2. If none: print success message, return `SUCCESS` (0).
3. Print summary and per-group detail (keep vs delete candidates).
4. If `--delete-duplicates`:
   - With `--dry-run`: print `[dry-run] Would delete transaction #…` for each skippable candidate.
   - Without `--dry-run`: call `DeleteTransaction::handle()` for each candidate not skipped by guards.
5. Print final counts (groups, deleted, skipped).
6. Return `FAILURE` (1) if any duplicate groups were found (even when only reporting).

Follow existing command conventions: PHP 8 attributes (`#[Signature]`, `#[Description]`), same style as `accounts:recalculate-balance` and `imports:purge-old-files`.

## Error handling and safety

| Situation | Behavior |
|-----------|----------|
| No duplicates | Info message, exit 0 |
| Duplicates found, report only | List groups, exit 1 |
| Candidate has non-null `transfer_id` | Skip, warn — do not call `DeleteTransaction` |
| Account is soft-deleted | Skip, warn |
| `DeleteTransaction` throws (e.g. incomplete transfer) | Catch, log warning, continue with next candidate |
| `--delete-duplicates` without `--dry-run` | Perform deletes; no interactive confirmation (scriptable) |

Deletion never bypasses `DeleteTransaction` — no raw `DELETE` queries.

## Testing

### Unit — `TransactionDuplicateFinderTest`

- No duplicates → empty result.
- Two identical rows (same date, amount, normalized description) → one group, correct `keep_id` / `duplicate_ids`.
- Manual duplicates (no `import_id`) are grouped.
- Same key on different accounts/users → single global group.
- Different amount or date → not grouped.

### Feature — `DetectDuplicatesCommandTest`

- Command with duplicates → exit 1, output contains group summary.
- No duplicates → exit 0.
- `--delete-duplicates --dry-run` → no DB changes, dry-run lines present.
- `--delete-duplicates` → removes only non-kept IDs; kept row remains.
- After delete, account `current_balance` matches expected (via `DeleteTransaction`).
- Transfer-linked duplicate candidate → skipped, row remains.

## Verification checklist

- [x] `TransactionDuplicateFinder` implemented with tests
- [x] `transactions:detect-duplicates` command registered and working
- [x] Report and delete modes behave as specified
- [x] Transfer-linked and soft-deleted account guards work
- [x] Pint on touched PHP files
- [x] Feature and unit tests pass

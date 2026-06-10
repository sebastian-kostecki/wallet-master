# Import fingerprint dedupe

**Date:** 2026-06-10  
**Status:** Approved (brainstorming)  
**Scope:** `transactions` schema, import commit workflow, transaction update workflow, import tests

## Problem

Import deduplication currently depends on `transactions.dedupe_hash`, which is calculated from the transaction date, amount, and normalized editable description. When a user imports a statement, edits imported transactions, and then imports the same statement again, edited rows can stop matching their original dedupe hash. The second import may then save duplicates instead of counting every row as `rows_skipped_duplicate`.

The immediate observed case was: first import saved 11 rows, then after editing imported transactions, importing the same statement again detected only 5 duplicates and saved 6 rows again.

## Goals

- Make import duplicate detection stable after user edits imported transactions.
- Keep user-editable fields (`date`, `booked_at`, `amount`, `description`, `subject`, category, pocket) independent from the immutable import identity.
- Preserve manual duplicate behavior: users can still manually create identical transactions.
- Keep the implementation aligned with the current import architecture and existing `raw_statement_description` source metadata.

## Non-goals

- Add bank-specific transaction IDs. MVP banks do not reliably export such IDs.
- Build an import preview or undo flow.
- Prevent users from manually creating duplicate-looking transactions.
- Rework `DescriptionMemory`; edited imported transactions should still teach description/category memory as they do today.

## Decisions

| Topic | Choice |
|-------|--------|
| Stable import identity | Add nullable `transactions.import_fingerprint` |
| Fingerprint input | `account_id + parsed date + parsed amount + normalized(raw_statement_description)` |
| Date source | Current parsed transaction date at import time |
| Edit behavior | `import_fingerprint` is never recalculated by transaction edits |
| Import duplicate check | Use `import_fingerprint` when present |
| Manual transactions | `import_fingerprint = null` |
| Uniqueness | Unique index on `account_id + import_fingerprint`; old `account_id + dedupe_hash` unique constraint is removed |

## Architecture

### Data model

Add `import_fingerprint` to `transactions` as a nullable binary fingerprint. It is set for rows created by file import and remains `null` for manual transactions, transfer legs, account balance adjustments, and other non-import rows.

Add a unique index for `account_id + import_fingerprint`. Because the column is nullable, manual transactions are not constrained by this index. Imported transactions get a stable per-account identity that does not change when user-facing fields are edited later.

Existing `dedupe_hash` remains in place for compatibility with current transaction creation/update flows, but it no longer owns the database uniqueness rule. Import duplicate detection and import duplicate prevention move to `import_fingerprint`.

### Fingerprint calculation

During import, after a row is normalized by the bank adapter:

1. Take the parsed date from the row.
2. Take the parsed signed amount from the row.
3. Take the original bank description from `rawStatementDescription`.
4. Normalize the raw description with the same whitespace/lowercase normalization style used by `TransactionDedupe`.
5. Hash `account_id`, parsed date, parsed amount, and normalized raw statement description.

This value is source-derived: it represents the bank statement row as imported, not the current edited transaction.

### Import workflow

`CommitImport` should calculate `import_fingerprint` before enrichment changes the visible description. The duplicate check should search for an existing transaction on the account with the same `import_fingerprint`. The in-file duplicate guard should also track fingerprints so duplicate rows inside the same uploaded file are counted once.

When the row is inserted, both fields can be stored:

- `import_fingerprint`: stable identity for future imports of the same statement row.
- `dedupe_hash`: existing transaction hash for current compatibility and non-import flows, without enforcing import uniqueness.

Supported bank adapters already require a non-empty description for a valid import row. Therefore `import_fingerprint` has no fallback path for an empty `raw_statement_description`; such rows continue to fail validation before insert.

### Transaction updates

`UpdateTransaction` must not change `import_fingerprint`. A user may edit description, subject, category, pocket, date, booked date, amount, or account according to existing validation rules. Those edits may still update `dedupe_hash` and `normalized_description`, but the import fingerprint remains the original value from import time.

This preserves the current `DescriptionMemory` behavior: editing an imported transaction can still remember corrections based on `raw_statement_description`.

### Backfill

For existing imported transactions with non-empty `raw_statement_description`, a migration or one-time data update can backfill `import_fingerprint` from current `account_id`, current `date`, current `amount`, and normalized `raw_statement_description`.

This cannot perfectly reconstruct historical source values if users already edited date or amount after import, but it improves protection for existing data without adding new source-date columns.

## Error Handling

The database unique index is the final protection against duplicate imported rows on the same account. The import workflow should still perform an application-level existence check first so counters remain accurate and duplicate rows are counted as skipped rather than surfacing as database errors.

If a race condition hits the unique index anyway, implementation should handle it as an import duplicate where practical, or fail the import with enough logging context to diagnose the row without exposing statement text in logs.

## Testing

Feature tests should cover:

- Import the same file twice without edits: second import imports `0` and skips all rows.
- Import a file, edit imported transaction descriptions, import the same file again: second import skips all rows.
- Import a file, edit imported transaction date or amount, import the same file again: second import skips all rows because `import_fingerprint` did not change.
- Manual identical transactions remain allowed because `import_fingerprint` is `null`.
- Two different import rows with different `date`, `amount`, or raw statement description are imported normally.
- Duplicate rows inside the same import file are skipped by the in-memory fingerprint guard.

## Verification Checklist

- [ ] Migration adds nullable `import_fingerprint` and the unique account fingerprint index.
- [ ] Migration removes the old unique constraint on `account_id + dedupe_hash`.
- [ ] `CommitImport` uses `import_fingerprint` for import dedupe.
- [ ] `UpdateTransaction` preserves existing `import_fingerprint`.
- [ ] Affected import and transaction tests pass.
- [ ] Pint runs on touched PHP files.


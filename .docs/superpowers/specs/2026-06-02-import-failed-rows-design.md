# Import Failed Rows — Design Spec

**Date:** 2026-06-02  
**Status:** Approved (brainstorming)  
**Related PRD:** Journey B (FR-I1), UX §9 import summary

## Problem

During CSV/XLSX import, some rows fail validation (`rows_failed_validation`). Today the user sees only a counter — not which rows failed or why. Without this, balance discrepancies vs. the bank statement are unexplained.

The user does **not** want an import history screen. They want to stay focused on transactions, with a clear signal when something from an import was skipped and why.

## Goals

1. Persist each validation-failed row with a human-readable reason and raw field snapshot (what to search for manually).
2. Show failed rows immediately after import (import modal result step).
3. Show a persistent, expandable banner on the transactions list until the user manually dismisses each row.
4. Log structured telemetry for observability (no full row data in production logs).

## Non-Goals

- Import history / imports list UI.
- Tracking skipped duplicates (expected behavior; no balance impact).
- Auto-matching dismissed rows to manually added transactions.
- Export of failed rows to CSV.
- Re-import / retry of individual failed rows from UI.

## Decisions (from brainstorming)

| Topic | Decision |
|---|---|
| Persistence | Dedicated table `import_failed_rows` |
| Banner location | Expandable banner on transactions index |
| Dismiss | Manual only (`dismissed_at` per row) |
| Import list UI | None |

---

## Data Model

### Table: `import_failed_rows`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `import_id` | FK → `imports` | cascade on import delete |
| `user_id` | FK → `users` | data isolation |
| `account_id` | FK → `accounts` | banner scoping |
| `row_number` | unsigned int | 1-based, excluding header row |
| `reason_code` | string | see enum below |
| `date_raw` | string, nullable | snapshot from file |
| `amount_raw` | string, nullable | snapshot from file |
| `description_raw` | string, nullable | max 2000 chars |
| `subject_raw` | string, nullable | max 255 chars |
| `dismissed_at` | timestamp, nullable | NULL = visible in banner |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**

- `(user_id, account_id, dismissed_at)` — banner query
- `(import_id)` — modal result / import detail

### Enum: `ImportFailedRowReason`

| Code | Source | User-facing i18n key |
|---|---|---|
| `missing_fields` | Empty date, amount, or description after trim | `imports.failed_rows.reasons.missing_fields` |
| `invalid_date` | `DateParser::parse()` failure | `imports.failed_rows.reasons.invalid_date` |
| `invalid_amount` | `AmountParser::parse()` failure (non-numeric, empty after cleanup) | `imports.failed_rows.reasons.invalid_amount` |
| `zero_amount` | Amount parses to zero | `imports.failed_rows.reasons.zero_amount` |

Mapping: catch `\Throwable` in `normalizeRow()` path; inspect exception message or use typed exceptions if refactor is minimal. Fallback code `invalid_row` only if unmapped (should not occur in normal flow).

### Retention

- Records persist indefinitely in DB.
- Dismissed rows remain for audit but are hidden from UI (`dismissed_at IS NOT NULL`).
- No cron purge in MVP.

---

## Backend Architecture

Follows Wallet Master Variant A conventions.

### New files

| Layer | File |
|---|---|
| Model | `App\Models\ImportFailedRow` |
| Enum | `App\Enums\ImportFailedRowReason` |
| Policy | `App\Policies\ImportFailedRowPolicy` |
| Action (write) | `App\Actions\Imports\DismissImportFailedRow` |
| Action (write) | `App\Actions\Imports\DismissAllImportFailedRows` (optional convenience for banner) |
| Resource | `App\Http\Resources\Imports\ImportFailedRowResource` |
| Controller | `App\Http\Controllers\Imports\ImportFailedRowController` (dismiss endpoints only) |
| Migration | `create_import_failed_rows_table` |

### Modified files

| File | Change |
|---|---|
| `CommitImport` | Capture failed rows + bulk insert per chunk; map exceptions to `reason_code`; extract raw fields before parse |
| `ListTransactions` | `handleUnresolvedImportFailedRows()` + getter `getUnresolvedImportFailedRows()` |
| `TransactionController::index()` | Pass `unresolved_import_failed_rows` via Resource |
| `ImportController::importDetailPayload()` | Include `failed_rows` (limit 50) |
| `ImportStatusUpdated` event | Include `failed_rows` in broadcast payload when status is `committed` |
| `Import` model | `hasMany` → `ImportFailedRow` |

### Import commit flow

```
For each file row:
  1. Increment row_number
  2. Extract raw field values from mapped columns
  3. Try normalizeRow + type inference
  4. On failure:
     - rows_failed_validation++
     - append to pendingFailedRows[]
     - Log::channel('telemetry')->info('import_row_validation_failed', {...})
     - Log::debug(...) with truncated description (non-production detail)
  5. On success: existing dedupe + insert logic

flushChunk():
  - bulk insert transactions (existing)
  - bulk insert import_failed_rows (new)
  - update import counters (existing)
```

Raw field extraction happens **before** parsing so the user sees exactly what was in the file.

### Dismiss flow

```
POST /import-failed-rows/{importFailedRow}/dismiss
  → authorize (owner, not already dismissed)
  → DismissImportFailedRow::handle($user, $row)
  → redirect back or JSON 204

POST /import-failed-rows/dismiss-all?account_id= (optional)
  → dismiss all unresolved for user (+ optional account filter)
```

After dismiss, transactions index reload removes row from banner (Inertia redirect or router.reload).

### List transactions query

```php
ImportFailedRow::query()
    ->where('user_id', $user->id)
    ->whereNull('dismissed_at')
    ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
    ->orderByDesc('created_at')
    ->orderBy('row_number')
    ->get();
```

When no account filter: return all unresolved rows (frontend groups by account name).

---

## Logging

Per PRD NFR §10 — no full import rows in production logs.

| Channel | Level | Payload |
|---|---|---|
| `telemetry` | info | `event: import_row_validation_failed`, `import_id`, `account_id`, `user_id`, `row_number`, `reason_code` |
| default | debug | above + `description_raw` truncated to 80 chars |

Never log entire file contents or all column values at info/warning in production.

---

## Frontend

### Component: `ImportFailedRowsBanner.vue`

- Props: `rows: ImportFailedRow[]`, `accounts: Account[]` (for grouping labels).
- Collapsed by default; amber/warning styling consistent with existing import warning block.
- Expanded table: row number, date_raw, amount_raw, description_raw, reason (i18n), dismiss button.
- Optional header action: dismiss all visible rows.
- Dismiss calls `POST` dismiss endpoint then `router.reload({ only: ['unresolved_import_failed_rows'] })`.

### Transactions `Index.vue`

- Render banner below filters when `unresolved_import_failed_rows.length > 0`.
- Group by account when no account filter is active.

### `ImportDialog.vue` (result step)

- When `rows_failed_validation > 0` and `failed_rows` present: collapsible list (read-only, no dismiss).
- Hint copy: rows did not affect balance; add manually or check source file.
- If more than 50 rows: show count + pointer to transactions banner.

### i18n keys (Polish UI)

- `imports.failed_rows.banner.title` — e.g. „{count} wierszy z importu nie zostało dodanych"
- `imports.failed_rows.banner.subtitle` — saldo może być niezgodne z wyciągiem
- `imports.failed_rows.actions.dismiss` — „Oznacz jako rozwiązane"
- `imports.failed_rows.actions.dismiss_all` — „Oznacz wszystkie jako rozwiązane"
- `imports.failed_rows.reasons.*` — per reason_code

---

## Edge Cases

| Case | Behavior |
|---|---|
| Import with 0 failed rows | No banner; modal shows success only |
| All rows fail validation | Import still `committed`; banner shows all rows; `rows_imported = 0` |
| Whole import fails (system error) | No `import_failed_rows` records; existing `failed` status + `error_summary` unchanged |
| Import to deleted account | Blocked at commit (existing); N/A |
| User dismisses row | Hidden from banner; remains in DB |
| Large file (500+ failed rows) | Bulk insert per chunk; banner lists all unresolved (paginate in UI only if performance issue — defer pagination to post-MVP unless list > 100 feels slow) |
| Cross-user access | Policy denies dismiss/view |

---

## Testing

### Feature tests (`tests/Feature/Imports/`)

1. **CommitImportFailedRowsTest** — file with invalid date/amount/zero/missing fields creates `import_failed_rows` with correct `reason_code` and raw snapshots; counters match.
2. **ImportFailedRowDismissTest** — owner can dismiss; sets `dismissed_at`; non-owner 403; double dismiss 422 or idempotent 204.
3. **ListTransactionsFailedRowsTest** — transactions index includes unresolved rows; excludes dismissed; respects account filter.

### Feature tests (`tests/Feature/Transactions/`)

4. Banner prop present when unresolved rows exist; absent when all dismissed.

### Unit tests (optional)

5. `ImportFailedRowReason` mapping from exception types if extracted to Support class.

Run: `php artisan test --compact --filter=ImportFailed`

---

## Security

- All queries scoped by `user_id`.
- `ImportFailedRowPolicy`: `dismiss` requires `$row->user_id === $user->id`.
- Rate limit dismiss endpoint: inherit general API limiter (60/min).
- No exposure of other users' failed rows via import ID enumeration (404/403).

---

## Acceptance Criteria

1. Given a file with 2 invalid rows and 10 valid rows  
   When import completes  
   Then `rows_failed_validation = 2`, 2 records in `import_failed_rows`, 10 transactions created.

2. Given unresolved failed rows for account A  
   When user views transactions filtered to account A  
   Then expandable banner shows those 2 rows with reason and raw data.

3. Given user clicks „Oznacz jako rozwiązane" on a row  
   When page reloads  
   Then row no longer appears in banner.

4. Given import modal completes with failures  
   When user sees result step  
   Then failed rows list is visible (read-only).

5. Given import row fails validation  
   When commit runs  
   Then `telemetry` log contains `import_row_validation_failed` with `reason_code` (no full row in production log).

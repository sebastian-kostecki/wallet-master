# BNP Paribas import — subject from Nadawca/Odbiorca

**Date:** 2026-06-02  
**Status:** Approved (brainstorming)  
**Scope:** `BnpParibasImportAdapter` and shared import support for subject resolution and sanitization.

## Goal

When importing BNP Paribas statements that expose separate **Nadawca** and **Odbiorca** columns, populate transaction `subject` from the correct column based on transaction amount sign, and strip long digit sequences (typically embedded account numbers) so they do not pollute display or Typesense learning.

## Decisions (confirmed)

| Topic | Decision |
|-------|----------|
| Column when amount > 0 | `Nadawca` |
| Column when amount < 0 | `Odbiorca` |
| Column when amount = 0 | None → `subject = null` |
| Other column empty | No fallback → `subject = null` |
| Digit removal | Remove runs of **≥6 consecutive digits** only; collapse whitespace; empty result → `null` |
| Manual `mapping.subject` | If user maps a single `subject` column at commit, use that column with the same sanitization (no sign-based column pick) |

## Recommended approach

**Approach 1:** Logic in `BnpParibasImportAdapter` + `App\Support\Imports\SubjectSanitizer` + extended mapping keys for snapshot consistency. No UI or commit API changes.

Rejected for now:

- **Approach 2:** Adapter-only without snapshot changes (failed rows lose `subject_raw`).
- **Approach 3:** New `BankImportAdapter` method + UI dual mapping (YAGNI).

## Business rules

```
amount > 0  → read Nadawca column (if mapped / present)
amount < 0  → read Odbiorca column (if mapped / present)
amount = 0  → subject = null

If chosen column is empty → subject = null (do not read the other column)

Sanitize: remove /\d{6,}/, trim, collapse spaces
If sanitized string is empty → subject = null
Limit stored subject to 255 characters (existing Str::limit)
```

### Examples

| Amount | Nadawca | Odbiorca | Result `subject` |
|--------|---------|----------|------------------|
| `100.00` | `123456789012 JAN KOWALSKI` | *(empty)* | `JAN KOWALSKI` |
| `-12.34` | `FOO` | `987654321098 SKLEP` | `SKLEP` |
| `100.00` | *(empty)* | `BAR` | `null` |
| `0.00` | `A` | `B` | `null` |
| `50.00` | `Firma 3M` | — | `Firma 3M` |

`subject_raw` (failed import rows): raw text from the **selected** column only, **without** digit sanitization.

## Architecture

### `SubjectSanitizer` (`app/Support/Imports/SubjectSanitizer.php`)

Stateless helper:

- `stripLongDigitRuns(string $text, int $minLength = 6): string`
- Optionally `normalizeWhitespace(string $text): string` or inline `preg_replace('/\s+/u', ' ', ...)`

No HTTP, no Eloquent.

### `BnpParibasImportAdapter`

**`defaultMapping(array $headers)`**

1. Try Polish headers: `Data transakcji`, `Kwota`, `Opis` (fallback `Typ transakcji`).
2. If required columns missing → return `null`.
3. If `Nadawca` header exists → `subject_positive` => header name.
4. If `Odbiorca` header exists → `subject_negative` => header name.
5. Do **not** set `mapping['subject']` for native PL exports.
6. Remove incorrect logic that compared `$amount > 0` on a column index from `findHeader('Kwota')`.

If Polish mapping fails, delegate to `parent::defaultMapping()` (English `date` / `amount` / `description` / `subject`).

**`normalizeRow(array $row, array $mapping)`**

1. Parse date, amount, description (unchanged).
2. Resolve raw subject:
   - If `mapping['subject']` set → read that column.
   - Else if `subject_positive` / `subject_negative` in mapping → pick by `AmountParser` sign.
   - Else → `null`.
3. Sanitize with `SubjectSanitizer`; apply `Str::limit(255)`.
4. Return `ParsedImportRow` as today.

### `ImportRowRawSnapshot`

When `mapping` contains `subject_positive` and/or `subject_negative`:

1. Parse amount from `mapping['amount']` via `AmountParser` (same sign rules).
2. Read raw string from the selected column only.
3. Do not sanitize digits for `subject_raw`.

When only `mapping['subject']` is set, keep current behavior.

### Unchanged

- Import UI, `StoreImportCommitRequest` validation rules
- mBank adapter
- `EnrichImportRowDescription` / Typesense (consumes already-parsed `subject`)
- `CommitImport` workflow (uses adapter + snapshot)

## Data flow

```
CSV row
  → defaultMapping (subject_positive / subject_negative)
  → normalizeRow
      → AmountParser (sign)
      → pick column
      → SubjectSanitizer
      → ParsedImportRow.subject
  → ImportRowRawSnapshot (on failure)
      → same column pick, raw text → subject_raw
```

## Testing

| Test file | Cases |
|-----------|--------|
| `tests/Unit/Support/Imports/SubjectSanitizerTest.php` | 6+ digit removal; short digits kept; whitespace collapse; empty after strip |
| `tests/Unit/Imports/BnpParibasImportAdapterTest.php` | positive/negative/zero; empty chosen column; no fallback; sanitization; manual `mapping.subject` |
| `tests/Unit/Imports/BankAdapterDefaultMappingTest.php` | PL headers include `subject_positive`/`subject_negative`, no `subject` |

Run: `vendor/bin/pint --dirty` and `php artisan test --compact` with filters for the above files.

## Error handling

No new exceptions. Empty required fields still throw `RuntimeException('Required import columns are empty.')`. Missing Nadawca/Odbiorca columns simply omit subject mapping keys; import proceeds without `subject`.

## Regression checklist

- [ ] English-header BNP fixture still maps `subject` via parent fallback
- [ ] mBank `Kategoria` → `subject` unchanged
- [ ] Commit import job with PL BNP row sets expected `subject` on transaction
- [ ] Failed row stores `subject_raw` from correct column

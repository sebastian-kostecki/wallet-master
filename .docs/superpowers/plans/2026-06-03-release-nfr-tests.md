# Release NFR & Gap Tests — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close checklist §12.3 (strict models in non-production), §10.1 import all-duplicates test, and confirm §12.1 `api` rate limit from telemetry phase — before manual QA.

**Architecture:** `Model::shouldBeStrict()` globally in `AppServiceProvider` when `! app()->isProduction()`. Migrate `Account`, `Import`, `ImportFailedRow`, `AccountBalanceAdjustment` from `$guarded = []` to explicit `$fillable` matching existing `create([...])` call sites. Feature test for duplicate-only CSV mirrors QA-B2.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4.

**Spec:** `.docs/superpowers/specs/2026-06-03-mvp-release-readiness-design.md` (Phase 3)

**Branch:** `improvement/release-nfr-tests` (from `develop` after a11y merged)

**Prerequisite:** Telemetry plan merged (`api` rate limiter exists).

---

## File map

| Action | Path |
|--------|------|
| Modify | `app/Providers/AppServiceProvider.php` |
| Modify | `app/Models/Account.php`, `Import.php`, `ImportFailedRow.php`, `AccountBalanceAdjustment.php` |
| Create | `tests/Feature/Imports/CommitImportAllDuplicatesTest.php` |
| Modify | `.docs/checklist.md` §10.1, §12 |

---

### Task 1: `Model::shouldBeStrict()` in non-production

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add to `boot()`** (after rate limiters)

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // ... existing rate limiters + event listeners ...

    if (! $this->app->isProduction()) {
        Model::shouldBeStrict();
    }
}
```

- [ ] **Step 2: Run full suite — expect failures** on lazy loading or unguarded mass assignment

Run: `./vendor/bin/sail artisan test --compact`  
Note failing tests/models.

- [ ] **Step 3: Commit infrastructure**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "chore: enable strict Eloquent models outside production"
```

---

### Task 2: `$fillable` on Account

**Files:**
- Modify: `app/Models/Account.php`
- Modify: any test factories using `Account::create` with extra keys

- [ ] **Step 1: Define fillable** (from `StoreAccount`, factories, migrations)

```php
protected $fillable = [
    'user_id',
    'currency_id',
    'name',
    'bank',
    'type',
    'opening_balance',
    'current_balance',
];

protected $guarded = [];
```

Remove `$guarded = []` or set `$guarded` unused — prefer only `$fillable`.

- [ ] **Step 2: Run** `./vendor/bin/sail artisan test --compact tests/Feature/Accounts/`

- [ ] **Step 3: Commit**

---

### Task 3: `$fillable` on Import + ImportFailedRow

**Files:**
- Modify: `app/Models/Import.php`
- Modify: `app/Models/ImportFailedRow.php`

- [ ] **Step 1: Inspect `Import::create` / `update` in** `CommitImport`, `ImportController`, factories.

Typical Import fillable:

```php
'user_id', 'account_id', 'status', 'mapping', 'rows_total',
'rows_imported', 'rows_skipped_duplicate', 'rows_failed_validation',
'details', 'committed_at', 'failed_at', /* paths if mass-assigned */
```

ImportFailedRow: `import_id`, `row_number`, `reason`, `raw_row`, `message` (match actual columns).

- [ ] **Step 2: Run** `./vendor/bin/sail artisan test --compact tests/Feature/Imports/`

- [ ] **Step 3: Commit**

---

### Task 4: `$fillable` on AccountBalanceAdjustment

**Files:**
- Modify: `app/Models/AccountBalanceAdjustment.php`

- [ ] **Step 1: Fillable from** `AdjustAccountBalance` create array.

- [ ] **Step 2: Run** tests touching balance adjustment.

- [ ] **Step 3: Commit**

---

### Task 5: Import file with only duplicates (QA-B2)

**Files:**
- Create: `tests/Feature/Imports/CommitImportAllDuplicatesTest.php`

- [ ] **Step 1: Write failing test**

Use existing MBank/BNP fixture pattern from `CommitImportJobTest.php`:

1. Create user, account, PLN.
2. Import valid file once → `rows_imported >= 1`.
3. Second import **same file** (or file containing only rows already in DB) → assert:
   - `rows_imported === 0`
   - `rows_skipped_duplicate > 0`
   - Import status `committed`

Optional: assert flash/toast `message_key` if exposed on redirect — only if controller sets it; otherwise DB assertions suffice for automated gate.

```php
test('import with only duplicate rows commits with zero imported', function () {
    // ... seed first import ...
    // ... second import same rows ...
    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run test** — should PASS without code change if dedupe already correct; if FAIL, fix `CommitImport` counter logic (minimal fix only).

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Imports/CommitImportAllDuplicatesTest.php`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Imports/CommitImportAllDuplicatesTest.php
git commit -m "test: import with only duplicates yields rows_imported zero"
```

---

### Task 6: Verify api rate limit + checklist

- [ ] **Step 1: Confirm** `RateLimiter::for('api', ...)` exists (from telemetry Task 2). If missing, add per telemetry plan.

- [ ] **Step 2: Optional test** — 61st request to `telemetry.store` returns 429 (use `withoutMiddleware` disabled, real throttle).

- [ ] **Step 3: Full verification**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact
./vendor/bin/phpstan analyse
```

- [ ] **Step 4: Update `.docs/checklist.md`** §10.1 (duplicate test), §12.1, §12.3.

- [ ] **Step 5: Commit docs**

---

## Self-review

| Spec Phase 3 item | Task |
|-------------------|------|
| `shouldBeStrict()` | Task 1 |
| `$fillable` minimum | Tasks 2–4 |
| All-duplicates import test | Task 5 |
| api rate limit | Task 6 (verify) |

`Currency` and `User` may remain as-is if tests pass; add fillable only if strict mode breaks them.

---

## After this plan

Proceed to **Phase 4** (manual QA) from spec — no implementation plan file; use QA table in spec §7.1.

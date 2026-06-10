# Import Fingerprint Dedupe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make file import deduplication stable after users edit imported transactions.

**Architecture:** Add a nullable immutable `transactions.import_fingerprint` for rows created by file import. Calculate it from `account_id + parsed date + parsed amount + normalized(raw_statement_description)` during import, use it for duplicate checks, and never recalculate it during transaction edits. Keep `dedupe_hash` for existing manual and compatibility flows, but remove its old database uniqueness responsibility.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, MySQL, Pest 4, Laravel Sail, Pint.

---

## File Structure

- Modify: `app/Support/Transactions/TransactionDedupe.php`
  - Add a focused `importFingerprint()` helper next to existing hash helpers.
- Modify: `app/Imports/Workflow/CommitImport.php`
  - Calculate and persist `import_fingerprint`.
  - Check duplicates by `account_id + import_fingerprint`.
  - Track in-file duplicates by fingerprint.
- Modify: `app/Models/Transaction.php`
  - Add `import_fingerprint` to fillable attributes and PHPDoc.
- Create: `database/migrations/2026_06_10_120000_add_import_fingerprint_to_transactions_table.php`
  - Add nullable binary column.
  - Drop the old unique constraint on `account_id + dedupe_hash` and replace it with a non-unique lookup index.
  - Backfill imported rows with raw statement descriptions.
  - Add unique index on `account_id + import_fingerprint`.
- Modify: `tests/Feature/Imports/CommitImportJobTest.php`
  - Add regression tests for re-import after editing imported fields.
  - Assert imported rows receive stable fingerprints.
- Verify: `tests/Feature/Transactions/ManualDuplicateAllowedTest.php`
  - Existing manual duplicate test should continue passing without changes.

## Task 1: Add Failing Import Fingerprint Tests

**Files:**
- Modify: `tests/Feature/Imports/CommitImportJobTest.php`

- [ ] **Step 1: Add a failing test for re-import after description edits**

Append this test after `job skips duplicates when importing same file twice`:

```php
test('job skips duplicates after imported transaction descriptions are edited', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $content = "date;amount;description;subject\n"
        ."24-04-2026;-12.34;Coffee;Cafe\n"
        ."25-04-2026;100.00;Salary;Work";

    $firstImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $firstImport->id])->handle(app(CommitImport::class));

    Transaction::query()
        ->where('import_id', $firstImport->id)
        ->orderBy('date')
        ->get()
        ->each(function (Transaction $transaction, int $index): void {
            $transaction->forceFill([
                'description' => "Edited description {$index}",
                'subject' => "Edited subject {$index}",
                'normalized_description' => "edited description {$index}",
                'dedupe_hash' => md5("changed-hash-{$index}", true),
            ])->save();
        });

    $secondImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $secondImport->id])->handle(app(CommitImport::class));

    $secondImport->refresh();

    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBe(2);
    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(2);
});
```

- [ ] **Step 2: Add a failing test for re-import after date and amount edits**

Append this test after the description edit test:

```php
test('job skips duplicates after imported transaction date and amount are edited', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $content = "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe";

    $firstImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $firstImport->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $firstImport->id)->firstOrFail();
    $transaction->forceFill([
        'date' => '2026-04-30',
        'booked_at' => '2026-04-30',
        'amount' => '-20.00',
        'dedupe_hash' => md5('changed-date-and-amount', true),
    ])->save();

    $secondImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $secondImport->id])->handle(app(CommitImport::class));

    $secondImport->refresh();

    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBe(1);
    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(1);
});
```

- [ ] **Step 3: Add a failing test for stored fingerprint values**

Append this test after the date and amount edit test:

```php
test('job stores import fingerprint for imported rows', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = createImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->firstOrFail();

    expect($transaction->import_fingerprint)->not->toBeNull();
    expect(bin2hex($transaction->import_fingerprint))->toBe(
        bin2hex(md5($account->id.'|2026-04-24|-12.34|coffee', true))
    );
});
```

- [ ] **Step 4: Run the targeted tests and confirm they fail**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Imports/CommitImportJobTest.php --filter='import fingerprint|after imported transaction'
```

Expected: FAIL because `import_fingerprint` column/model support does not exist yet.

## Task 2: Add Schema And Model Support

**Files:**
- Create: `database/migrations/2026_06_10_120000_add_import_fingerprint_to_transactions_table.php`
- Modify: `app/Models/Transaction.php`
- Modify: `app/Support/Transactions/TransactionDedupe.php`
- Test: `tests/Feature/Imports/CommitImportJobTest.php`

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_06_10_120000_add_import_fingerprint_to_transactions_table.php`:

```php
<?php

declare(strict_types=1);

use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->binary('import_fingerprint', 16)->nullable()->after('dedupe_hash');
            $table->dropUnique(['account_id', 'dedupe_hash']);
            $table->index(['account_id', 'dedupe_hash']);
        });

        $transactions = DB::table('transactions')
            ->whereNotNull('import_id')
            ->whereNotNull('raw_statement_description')
            ->orderBy('id')
            ->select(['id', 'account_id', 'date', 'amount', 'raw_statement_description'])
            ->lazyById();

        /** @var array<string, true> $seenBackfillFingerprints */
        $seenBackfillFingerprints = [];

        foreach ($transactions as $transaction) {
            $normalizedRaw = TransactionDedupe::normalizeDescription((string) $transaction->raw_statement_description);
            $fingerprint = TransactionDedupe::importFingerprint(
                accountId: (int) $transaction->account_id,
                dateYmd: (string) $transaction->date,
                amountDecimalString: TransactionDedupe::amountToDecimalString((string) $transaction->amount),
                normalizedRawStatementDescription: $normalizedRaw,
            );
            $fingerprintKey = (int) $transaction->account_id.'|'.bin2hex($fingerprint);

            if (isset($seenBackfillFingerprints[$fingerprintKey])) {
                continue;
            }

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'import_fingerprint' => $fingerprint,
                ]);

            $seenBackfillFingerprints[$fingerprintKey] = true;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->unique(['account_id', 'import_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['account_id', 'import_fingerprint']);
            $table->dropIndex(['account_id', 'dedupe_hash']);
            $table->unique(['account_id', 'dedupe_hash']);
            $table->dropColumn('import_fingerprint');
        });
    }
};
```

The backfill intentionally leaves historical duplicate collisions as `null`. This lets the unique `import_fingerprint` index be added even if duplicate rows already exist from the old behavior, while future imports are protected. The old `account_id + dedupe_hash` unique constraint is replaced by a non-unique index because `dedupe_hash` is no longer the source of import identity.

- [ ] **Step 2: Add the fingerprint helper**

Update `app/Support/Transactions/TransactionDedupe.php` by adding this method after `dedupeHash()`:

```php
/**
 * @param  numeric-string  $amountDecimalString
 */
public static function importFingerprint(
    int $accountId,
    string $dateYmd,
    string $amountDecimalString,
    string $normalizedRawStatementDescription,
): string {
    return md5($accountId.'|'.$dateYmd.'|'.$amountDecimalString.'|'.$normalizedRawStatementDescription, true);
}
```

- [ ] **Step 3: Update the Transaction model**

In `app/Models/Transaction.php`, add this PHPDoc property near the existing import metadata:

```php
 * @property string|null $raw_statement_description
 * @property string|null $import_fingerprint
```

Add `import_fingerprint` to `$fillable` after `dedupe_hash`:

```php
'dedupe_hash',
'import_fingerprint',
'transfer_id',
```

No cast is required for the binary field because the code stores and compares the raw 16-byte string.

- [ ] **Step 4: Run the targeted tests and confirm behavior changed**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Imports/CommitImportJobTest.php --filter='stores import fingerprint'
```

Expected: FAIL because `CommitImport` does not write `import_fingerprint` yet.

## Task 3: Use Import Fingerprints In CommitImport

**Files:**
- Modify: `app/Imports/Workflow/CommitImport.php`
- Test: `tests/Feature/Imports/CommitImportJobTest.php`

- [ ] **Step 1: Rename the in-memory duplicate guard variable**

In `app/Imports/Workflow/CommitImport.php`, replace the setup around the pending arrays:

```php
/** @var array<string, true> $seenImportFingerprints */
$seenImportFingerprints = [];
/** @var list<array<string, mixed>> $pendingInserts */
$pendingInserts = [];
```

Then pass `seenImportFingerprints: $seenImportFingerprints` into `buildInsertRow()`.

- [ ] **Step 2: Update the `buildInsertRow()` signature and PHPDoc**

Replace the existing `$seenDedupeHashes` references in the docblock and signature with:

```php
 * @param  array<string, true>  $seenImportFingerprints
```

and:

```php
array &$seenImportFingerprints,
```

- [ ] **Step 3: Calculate the import fingerprint from source row data**

Inside `buildInsertRow()`, replace the current normalized description and duplicate check block with:

```php
$normalizedRawStatementDescription = TransactionDedupe::normalizeDescription($parsedRow->rawStatementDescription);
$importFingerprint = TransactionDedupe::importFingerprint(
    accountId: (int) $account->id,
    dateYmd: $parsedRow->date,
    amountDecimalString: $parsedRow->amount,
    normalizedRawStatementDescription: $normalizedRawStatementDescription,
);
$importFingerprintKey = bin2hex($importFingerprint);

if (isset($seenImportFingerprints[$importFingerprintKey])) {
    $counters->rowsSkippedDuplicate++;

    return null;
}

$exists = Transaction::query()
    ->where('account_id', $account->id)
    ->where('import_fingerprint', $importFingerprint)
    ->exists();

if ($exists) {
    $counters->rowsSkippedDuplicate++;

    return null;
}

$seenImportFingerprints[$importFingerprintKey] = true;
$normalizedDescription = TransactionDedupe::normalizeDescription($parsedRow->description);
$dedupeHash = TransactionDedupe::dedupeHash($parsedRow->date, $parsedRow->amount, $normalizedDescription);
```

- [ ] **Step 4: Persist the fingerprint on insert**

In the returned insert row, add `import_fingerprint` after `dedupe_hash`:

```php
'normalized_description' => $normalizedDescription,
'dedupe_hash' => $dedupeHash,
'import_fingerprint' => $importFingerprint,
'category_id' => $categoryId,
```

- [ ] **Step 5: Run the import fingerprint tests**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Imports/CommitImportJobTest.php --filter='import fingerprint|after imported transaction|same file twice'
```

Expected: PASS.

## Task 4: Confirm UpdateTransaction Preserves Fingerprints

**Files:**
- Modify: `tests/Feature/Transactions/TransactionUpdateTest.php`
- Test: `tests/Feature/Transactions/TransactionUpdateTest.php`

- [ ] **Step 1: Add a regression test for update preservation**

Append this test to `tests/Feature/Transactions/TransactionUpdateTest.php`:

```php
test('updating an imported transaction preserves import fingerprint', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Imported',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'committed',
        'mapping' => [],
        'details' => [],
        'rows_total' => 0,
        'rows_imported' => 0,
        'rows_skipped_duplicate' => 0,
        'rows_failed_validation' => 0,
    ]);

    $fingerprint = md5($account->id.'|2026-04-20|-10.00|atm cash out', true);
    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'import_id' => $import->id,
        'raw_statement_description' => 'ATM CASH OUT',
        'import_fingerprint' => $fingerprint,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'ATM CASH OUT',
        'subject' => null,
        'normalized_description' => 'atm cash out',
        'dedupe_hash' => md5('2026-04-20|-10.00|atm cash out', true),
    ]);

    $this
        ->actingAs($user)
        ->put(route('transactions.update', $transaction, absolute: false), [
            'account_id' => $account->id,
            'date' => '21-04-2026',
            'amount' => -20,
            'description' => 'Cash withdrawal',
            'subject' => 'ATM',
            'category_id' => $transaction->category_id,
        ])
        ->assertSessionHasNoErrors();

    $transaction->refresh();

    expect($transaction->import_fingerprint)->toBe($fingerprint);
});
```

- [ ] **Step 2: Run the update preservation test**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionUpdateTest.php --filter='preserves import fingerprint'
```

Expected: PASS without changing `UpdateTransaction`, because the action never assigns `import_fingerprint`.

## Task 5: Full Verification And Cleanup

**Files:**
- Verify: `app/Support/Transactions/TransactionDedupe.php`
- Verify: `app/Imports/Workflow/CommitImport.php`
- Verify: `app/Models/Transaction.php`
- Verify: `database/migrations/2026_06_10_120000_add_import_fingerprint_to_transactions_table.php`
- Verify: `tests/Feature/Imports/CommitImportJobTest.php`
- Verify: `tests/Feature/Transactions/TransactionUpdateTest.php`

- [ ] **Step 1: Run Pint on dirty PHP files**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Expected: Pint completes and reports formatted or unchanged files.

- [ ] **Step 2: Run focused import and transaction tests**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Imports/CommitImportJobTest.php tests/Feature/Imports/CommitImportAllDuplicatesTest.php tests/Feature/Transactions/TransactionUpdateTest.php tests/Feature/Transactions/ManualDuplicateAllowedTest.php
```

Expected: PASS.

- [ ] **Step 3: Run the broader imports domain tests**

Run:

```bash
./vendor/bin/sail artisan test --compact --filter=Import
```

Expected: PASS.

- [ ] **Step 4: Review schema and code diff**

Run:

```bash
git diff -- database/migrations/2026_06_10_120000_add_import_fingerprint_to_transactions_table.php app/Support/Transactions/TransactionDedupe.php app/Imports/Workflow/CommitImport.php app/Models/Transaction.php tests/Feature/Imports/CommitImportJobTest.php tests/Feature/Transactions/TransactionUpdateTest.php
```

Expected: Diff only contains the import fingerprint schema, helper, import dedupe changes, model fillable/PHPDoc, and targeted tests.

## Self-Review

- Spec coverage: The plan adds `import_fingerprint`, calculates it from `account_id + date + amount + normalized(raw_statement_description)`, uses it for import dedupe, removes the old `dedupe_hash` uniqueness responsibility, keeps manual duplicates nullable, and verifies update preservation.
- Placeholder scan: No open implementation placeholders remain in the tasks.
- Type consistency: The helper name is consistently `TransactionDedupe::importFingerprint()`, the column is consistently `import_fingerprint`, and the import workflow variable is consistently `seenImportFingerprints`.


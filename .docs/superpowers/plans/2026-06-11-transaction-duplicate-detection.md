# Transaction Duplicate Detection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `transactions:detect-duplicates` to audit globally identical transactions and optionally delete redundant rows.

**Architecture:** Pure grouping logic in `Support/Transactions/TransactionDuplicateFinder` (chunk load, canonical logical key, PHP grouping). Thin Artisan command orchestrates reporting and delegates deletes to existing `DeleteTransaction`. No raw `DELETE` queries.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, MySQL, Pest 4, Laravel Sail, Pint.

**Spec:** `.docs/superpowers/specs/2026-06-11-transaction-duplicate-detection-design.md`

---

## File Structure

- Create: `app/Support/Transactions/TransactionDuplicateFinder.php`
  - `findGroups(): array` — returns duplicate groups with `key`, `keep_id`, `duplicate_ids`, `transactions`.
- Create: `app/Console/Commands/Transactions/DetectDuplicates.php`
  - Signature `transactions:detect-duplicates` with `--delete-duplicates` and `--dry-run`.
- Create: `tests/Unit/Support/Transactions/TransactionDuplicateFinderTest.php`
- Create: `tests/Feature/Transactions/DetectDuplicatesCommandTest.php`

---

## Task 1: Unit tests for `TransactionDuplicateFinder`

**Files:**
- Create: `tests/Unit/Support/Transactions/TransactionDuplicateFinderTest.php`

- [ ] **Step 1: Write failing unit tests**

```php
<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDuplicateFinder;
use Database\Seeders\CurrencySeeder;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createAccountForDuplicateTests(User $user): Account
{
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    return Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
}

function createLogicalDuplicateRow(
    User $user,
    Account $account,
    array $overrides = [],
): Transaction {
    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $account->currency_id,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => '-49.99',
        'type' => 'expense',
        'description' => 'Biedronka',
        'subject' => null,
        'normalized_description' => 'biedronka',
        'dedupe_hash' => md5('finder-'.uniqid('', true), true),
    ], $overrides));
}

test('finder returns empty array when no duplicates exist', function () {
    $user = User::factory()->create();
    $account = createAccountForDuplicateTests($user);

    createLogicalDuplicateRow($user, $account, [
        'date' => '2026-04-20',
        'amount' => '-10.00',
        'normalized_description' => 'coffee',
        'description' => 'Coffee',
    ]);

    createLogicalDuplicateRow($user, $account, [
        'date' => '2026-04-21',
        'amount' => '-20.00',
        'normalized_description' => 'groceries',
        'description' => 'Groceries',
    ]);

    expect(app(TransactionDuplicateFinder::class)->findGroups())->toBe([]);
});

test('finder groups identical rows and keeps lowest id', function () {
    $user = User::factory()->create();
    $account = createAccountForDuplicateTests($user);

    $first = createLogicalDuplicateRow($user, $account);
    $second = createLogicalDuplicateRow($user, $account, [
        'dedupe_hash' => md5('finder-second', true),
    ]);

    $groups = app(TransactionDuplicateFinder::class)->findGroups();

    expect($groups)->toHaveCount(1);
    expect($groups[0]['keep_id'])->toBe($first->id);
    expect($groups[0]['duplicate_ids'])->toBe([$second->id]);
    expect($groups[0]['key'])->toMatchArray([
        'date' => '2026-04-20',
        'amount' => '-49.99',
        'description' => 'biedronka',
    ]);
});

test('finder groups manual duplicates without import_id', function () {
    $user = User::factory()->create();
    $account = createAccountForDuplicateTests($user);

    createLogicalDuplicateRow($user, $account, ['import_id' => null]);
    createLogicalDuplicateRow($user, $account, ['import_id' => null]);

    expect(app(TransactionDuplicateFinder::class)->findGroups())->toHaveCount(1);
});

test('finder groups same logical key across different accounts', function () {
    $user = User::factory()->create();
    $accountA = createAccountForDuplicateTests($user);
    $accountB = createAccountForDuplicateTests($user);

    createLogicalDuplicateRow($user, $accountA);
    createLogicalDuplicateRow($user, $accountB);

    $groups = app(TransactionDuplicateFinder::class)->findGroups();

    expect($groups)->toHaveCount(1);
    expect($groups[0]['transactions'])->toHaveCount(2);
});

test('finder normalizes description when normalized_description is empty', function () {
    $user = User::factory()->create();
    $account = createAccountForDuplicateTests($user);

    createLogicalDuplicateRow($user, $account, [
        'description' => '  BIEDRONKA  ',
        'normalized_description' => '',
    ]);
    createLogicalDuplicateRow($user, $account, [
        'description' => 'biedronka',
        'normalized_description' => '',
        'dedupe_hash' => md5('finder-normalized', true),
    ]);

    expect(app(TransactionDuplicateFinder::class)->findGroups())->toHaveCount(1);
});

test('finder does not group rows with different amount or date', function () {
    $user = User::factory()->create();
    $account = createAccountForDuplicateTests($user);

    createLogicalDuplicateRow($user, $account);
    createLogicalDuplicateRow($user, $account, ['amount' => '-50.00']);
    createLogicalDuplicateRow($user, $account, ['date' => '2026-04-21', 'booked_at' => '2026-04-21']);

    expect(app(TransactionDuplicateFinder::class)->findGroups())->toHaveCount(1);
    expect(app(TransactionDuplicateFinder::class)->findGroups()[0]['duplicate_ids'])->toHaveCount(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Transactions/TransactionDuplicateFinderTest.php`

Expected: FAIL — class `TransactionDuplicateFinder` not found.

---

## Task 2: Implement `TransactionDuplicateFinder`

**Files:**
- Create: `app/Support/Transactions/TransactionDuplicateFinder.php`

- [ ] **Step 1: Implement finder**

```php
<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

final class TransactionDuplicateFinder
{
    /**
     * @return list<array{
     *     key: array{date: string, amount: string, description: string},
     *     keep_id: int,
     *     duplicate_ids: list<int>,
     *     transactions: list<array{
     *         id: int,
     *         account_id: int,
     *         user_id: int,
     *         import_id: int|null,
     *         transfer_id: string|null,
     *     }>
     * }>
     */
    public function findGroups(): array
    {
        /** @var array<string, list<array{
         *     id: int,
         *     account_id: int,
         *     user_id: int,
         *     import_id: int|null,
         *     transfer_id: string|null,
         *     date: string,
         *     amount: string,
         *     description: string,
         * }>> $buckets
         */
        $buckets = [];

        Transaction::query()
            ->orderBy('id')
            ->select([
                'id',
                'account_id',
                'user_id',
                'import_id',
                'transfer_id',
                'date',
                'amount',
                'description',
                'normalized_description',
            ])
            ->chunkById(500, function ($transactions) use (&$buckets): void {
                foreach ($transactions as $transaction) {
                    $logicalDescription = trim((string) $transaction->normalized_description) !== ''
                        ? (string) $transaction->normalized_description
                        : TransactionDedupe::normalizeDescription((string) $transaction->description);

                    $date = Carbon::parse((string) $transaction->date)->toDateString();
                    $amount = TransactionDedupe::amountToDecimalString((string) $transaction->amount);
                    $bucketKey = $date.'|'.$amount.'|'.$logicalDescription;

                    $buckets[$bucketKey] ??= [];
                    $buckets[$bucketKey][] = [
                        'id' => (int) $transaction->id,
                        'account_id' => (int) $transaction->account_id,
                        'user_id' => (int) $transaction->user_id,
                        'import_id' => $transaction->import_id !== null ? (int) $transaction->import_id : null,
                        'transfer_id' => $transaction->transfer_id !== null && $transaction->transfer_id !== ''
                            ? (string) $transaction->transfer_id
                            : null,
                        'date' => $date,
                        'amount' => $amount,
                        'description' => $logicalDescription,
                    ];
                }
            });

        $groups = [];

        foreach ($buckets as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            usort($rows, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

            $keep = $rows[0];
            $duplicateIds = array_values(array_map(
                fn (array $row): int => $row['id'],
                array_slice($rows, 1),
            ));

            $groups[] = [
                'key' => [
                    'date' => $keep['date'],
                    'amount' => $keep['amount'],
                    'description' => $keep['description'],
                ],
                'keep_id' => $keep['id'],
                'duplicate_ids' => $duplicateIds,
                'transactions' => array_map(fn (array $row): array => [
                    'id' => $row['id'],
                    'account_id' => $row['account_id'],
                    'user_id' => $row['user_id'],
                    'import_id' => $row['import_id'],
                    'transfer_id' => $row['transfer_id'],
                ], $rows),
            ];
        }

        usort($groups, fn (array $a, array $b): int => $a['keep_id'] <=> $b['keep_id']);

        return $groups;
    }
}
```

- [ ] **Step 2: Run unit tests**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Transactions/TransactionDuplicateFinderTest.php`

Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

## Task 3: Feature tests for Artisan command

**Files:**
- Create: `tests/Feature/Transactions/DetectDuplicatesCommandTest.php`

- [ ] **Step 1: Write failing feature tests**

Cover:
- no duplicates → exit `0`, success message
- duplicates present → exit `1`, summary in output
- `--delete-duplicates --dry-run` → no rows deleted, `[dry-run]` lines
- `--delete-duplicates` → deletes only newer IDs, keeps `MIN(id)`
- account balance updated after delete (set `current_balance` to include duplicate amounts before command)
- transfer-linked duplicate candidate skipped (create pair with same logical fields + `transfer_id`; assert row remains and warning in output)

Use helpers from unit test file or duplicate minimal setup inline. Invoke command via:

```php
$this->artisan('transactions:detect-duplicates')->assertExitCode(1);
```

For transfer skip test, create two transactions with same date/amount/description but one (or both) has `transfer_id` set; only the non-transfer duplicate should be deletable, or if candidate has `transfer_id`, skip it.

- [ ] **Step 2: Run feature tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/DetectDuplicatesCommandTest.php`

Expected: FAIL — command not found.

---

## Task 4: Implement `DetectDuplicates` command

**Files:**
- Create: `app/Console/Commands/Transactions/DetectDuplicates.php`

- [ ] **Step 1: Implement command**

Key behavior:
- Inject `TransactionDuplicateFinder` and `DeleteTransaction`.
- Report mode: print `Found X duplicate group(s), Y redundant row(s).` then per-group lines with `keep` / `delete` and metadata (`account`, `user`, `import`).
- `--delete-duplicates`:
  - Build map `id => group row` from finder output.
  - For each `duplicate_id`:
    - Skip with warning if `transfer_id !== null`.
    - Load account; skip with warning if `$account->trashed()`.
    - `--dry-run`: `[dry-run] Would delete transaction #ID (group keep #KEEP_ID)`.
    - Else: `try { $deleteTransaction->handle($transaction); } catch (Throwable $e) { $this->warn(...); continue; }`
- Final summary: groups found, deleted count, skipped count.
- Return `FAILURE` when groups found, else `SUCCESS`.

Follow style of `RecalculateAccountBalance` and `PurgeOldImportFiles` (PHP 8 attributes, `final class`).

- [ ] **Step 2: Run feature tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/DetectDuplicatesCommandTest.php`

Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

## Task 5: Final verification

- [ ] **Step 1: Run scoped test suite**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Transactions/TransactionDuplicateFinderTest.php tests/Feature/Transactions/DetectDuplicatesCommandTest.php`

- [ ] **Step 2: Smoke-test command locally**

Run: `./vendor/bin/sail artisan transactions:detect-duplicates`

Expected: success message when DB has no logical duplicates.

- [ ] **Step 3: Update spec checklist**

In `.docs/superpowers/specs/2026-06-11-transaction-duplicate-detection-design.md`, mark verification checklist items complete.

---

## Notes for implementer

- **Logical key date:** use `date` column (not `booked_at`) — matches spec and import dedupe semantics.
- **Delete order:** delete higher IDs first within a group (optional but avoids edge cases if FKs ever reference transaction order).
- **Intentional manual duplicates:** command will flag them; that is expected for ops cleanup, not a product bug.
- **Global scope:** two users with coincidentally identical rows will appear in one group — by design per spec.

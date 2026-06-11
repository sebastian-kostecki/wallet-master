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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

test('finder does not group rows with different amount', function () {
    $user = User::factory()->create();
    $account = createAccountForDuplicateTests($user);

    createLogicalDuplicateRow($user, $account);
    createLogicalDuplicateRow($user, $account);
    createLogicalDuplicateRow($user, $account, ['amount' => '-50.00']);

    $groups = app(TransactionDuplicateFinder::class)->findGroups();

    expect($groups)->toHaveCount(1);
    expect($groups[0]['duplicate_ids'])->toHaveCount(1);
});

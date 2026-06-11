<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createDuplicateCommandAccount(User $user): Account
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

function createDuplicateCommandTransaction(
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
        'dedupe_hash' => md5('command-'.uniqid('', true), true),
    ], $overrides));
}

test('detect duplicates command exits successfully when no duplicates exist', function () {
    $user = User::factory()->create();
    $account = createDuplicateCommandAccount($user);

    createDuplicateCommandTransaction($user, $account, [
        'normalized_description' => 'coffee',
        'description' => 'Coffee',
        'amount' => '-10.00',
    ]);

    $this->artisan('transactions:detect-duplicates')
        ->expectsOutputToContain('No logical duplicate transaction groups found.')
        ->assertExitCode(0);
});

test('detect duplicates command reports groups and exits with failure code', function () {
    $user = User::factory()->create();
    $account = createDuplicateCommandAccount($user);

    $first = createDuplicateCommandTransaction($user, $account);
    createDuplicateCommandTransaction($user, $account, [
        'dedupe_hash' => md5('command-second', true),
    ]);

    $this->artisan('transactions:detect-duplicates')
        ->expectsOutputToContain('Found 1 duplicate group(s), 1 redundant row(s).')
        ->expectsOutputToContain('keep:')
        ->expectsOutputToContain("#{$first->id}")
        ->assertExitCode(1);
});

test('detect duplicates dry run does not delete rows', function () {
    $user = User::factory()->create();
    $account = createDuplicateCommandAccount($user);

    createDuplicateCommandTransaction($user, $account);
    createDuplicateCommandTransaction($user, $account, [
        'dedupe_hash' => md5('command-dry-run', true),
    ]);

    $this->artisan('transactions:detect-duplicates', [
        '--delete-duplicates' => true,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('[dry-run] Would delete transaction #')
        ->assertExitCode(1);

    expect(Transaction::query()->count())->toBe(2);
});

test('detect duplicates command deletes redundant rows and keeps oldest id', function () {
    $user = User::factory()->create();
    $account = createDuplicateCommandAccount($user);

    Artisan::call('accounts:set-balance', [
        'account' => (string) $account->id,
        'balance' => '-99.98',
    ]);

    $first = createDuplicateCommandTransaction($user, $account);
    $second = createDuplicateCommandTransaction($user, $account, [
        'dedupe_hash' => md5('command-delete', true),
    ]);

    $this->artisan('transactions:detect-duplicates', [
        '--delete-duplicates' => true,
    ])->assertExitCode(1);

    expect(Transaction::query()->pluck('id')->all())->toBe([$first->id]);
    expect(Transaction::query()->find($second->id))->toBeNull();
    $account->refresh();
    expect(TransactionDedupe::amountToDecimalString((string) $account->current_balance))->toBe('-49.99');
});

test('detect duplicates command skips transfer linked candidates', function () {
    $user = User::factory()->create();
    $account = createDuplicateCommandAccount($user);

    createDuplicateCommandTransaction($user, $account);
    $transferDuplicate = createDuplicateCommandTransaction($user, $account, [
        'transfer_id' => '11111111-1111-1111-1111-111111111111',
        'dedupe_hash' => md5('command-transfer', true),
    ]);

    $this->artisan('transactions:detect-duplicates', [
        '--delete-duplicates' => true,
    ])
        ->expectsOutputToContain('Skipping transaction #'.$transferDuplicate->id.' (transfer-linked)')
        ->assertExitCode(1);

    expect(Transaction::query()->count())->toBe(2);
    expect(Transaction::query()->find($transferDuplicate->id))->not->toBeNull();
});

test('detect duplicates command skips candidates on soft deleted accounts', function () {
    $user = User::factory()->create();
    $account = createDuplicateCommandAccount($user);

    createDuplicateCommandTransaction($user, $account);
    $duplicate = createDuplicateCommandTransaction($user, $account, [
        'dedupe_hash' => md5('command-soft-delete', true),
    ]);

    $account->delete();

    $this->artisan('transactions:detect-duplicates', [
        '--delete-duplicates' => true,
    ])
        ->expectsOutputToContain('Skipping transaction #'.$duplicate->id.' (account #'.$account->id.' is soft-deleted)')
        ->assertExitCode(1);

    expect(Transaction::query()->count())->toBe(2);
});

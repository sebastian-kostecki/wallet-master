<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
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

test('recalculate-balance fixes drifted current balance from transaction sum', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 50,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-05-01',
        'booked_at' => '2026-05-01',
        'amount' => '-20.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'subject' => null,
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('2026-05-01|-20.00|coffee', true),
    ]);

    Artisan::call('accounts:recalculate-balance', [
        'account' => (string) $account->id,
    ]);

    $account->refresh();
    expect((string) $account->current_balance)->toBe('80.00');
});

test('recalculate-balance dry-run does not persist correction', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 50,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-05-01',
        'booked_at' => '2026-05-01',
        'amount' => '-20.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'subject' => null,
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('2026-05-01|-20.00|coffee', true),
    ]);

    Artisan::call('accounts:recalculate-balance', [
        'account' => (string) $account->id,
        '--dry-run' => true,
    ]);

    $account->refresh();
    expect((string) $account->current_balance)->toBe('50.00');
});

test('recalculate-balance includes adjustment transactions in the sum', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $delta = '15.00';
    $description = 'Korekta salda';
    $normalized = TransactionDedupe::normalizeDescription($description);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-05-01',
        'booked_at' => '2026-05-01',
        'amount' => $delta,
        'type' => TransactionType::Adjustment,
        'description' => $description,
        'subject' => null,
        'normalized_description' => $normalized,
        'dedupe_hash' => md5('adj-1|balance-adjustment', true),
    ]);

    $account->current_balance = '115.00';
    $account->save();

    Artisan::call('accounts:recalculate-balance', [
        'account' => (string) $account->id,
    ]);

    $account->refresh();
    expect((string) $account->current_balance)->toBe('115.00');
});

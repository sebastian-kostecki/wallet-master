<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('set-balance updates current balance without creating transactions', function () {
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

    Artisan::call('accounts:set-balance', [
        'account' => (string) $account->id,
        'balance' => '200.00',
    ]);

    $account->refresh();
    expect((string) $account->current_balance)->toBe('200.00');
    expect(Transaction::query()->count())->toBe(1);
});

test('set-balance dry-run does not persist change', function () {
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

    Artisan::call('accounts:set-balance', [
        'account' => (string) $account->id,
        'balance' => '200.00',
        '--dry-run' => true,
    ]);

    $account->refresh();
    expect((string) $account->current_balance)->toBe('50.00');
});

test('set-balance skips save when balance already matches', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => '150.00',
    ]);

    $exitCode = Artisan::call('accounts:set-balance', [
        'account' => (string) $account->id,
        'balance' => '150.00',
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('already matches');
    $account->refresh();
    expect((string) $account->current_balance)->toBe('150.00');
});

test('set-balance fails when account does not exist', function () {
    $exitCode = Artisan::call('accounts:set-balance', [
        'account' => '999999',
        'balance' => '100.00',
    ]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('Account not found.');
});

<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('updating a transaction updates balance by delta', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 90,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Old',
        'subject' => null,
        'normalized_description' => 'old',
        'dedupe_hash' => md5('2026-04-20|-10.00|old', true),
    ]);

    $this
        ->actingAs($user)
        ->put("/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => -20,
            'description' => 'New',
            'subject' => null,
        ])
        ->assertSessionHasNoErrors();

    $account->refresh();
    expect($account->current_balance)->toBe('80.00');
});

test('cannot update a transaction on a deleted account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Deleted',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'amount' => 10,
        'type' => 'income',
        'description' => 'Income',
        'subject' => null,
        'normalized_description' => 'income',
        'dedupe_hash' => md5('2026-04-20|10.00|income', true),
    ]);

    $account->delete();

    $this
        ->actingAs($user)
        ->put("/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => 20,
            'description' => 'Income',
        ])
        ->assertForbidden();
});


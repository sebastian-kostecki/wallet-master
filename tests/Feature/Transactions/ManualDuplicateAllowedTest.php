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

test('user can manually create two transactions with identical fields', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $payload = [
        'account_id' => $account->id,
        'date' => '24-04-2026',
        'amount' => -10.50,
        'description' => 'Duplicate-friendly coffee',
        'subject' => 'Cafe',
    ];

    $first = $this->actingAs($user)->post('/transactions', $payload);
    $first->assertSessionHasNoErrors();

    $second = $this->actingAs($user)->post('/transactions', $payload);
    $second->assertSessionHasNoErrors();

    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(2);
});

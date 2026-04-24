<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\AccountBalanceAdjustment;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can set current balance and adjustment is audited', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 130,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 999.99,
        ]);

    $response->assertSessionHasNoErrors();

    $account->refresh();
    expect($account->current_balance)->toBe('999.99');

    $adjustment = AccountBalanceAdjustment::query()->where('account_id', $account->id)->first();
    expect($adjustment)->not->toBeNull();
    expect($adjustment->user_id)->toBe($user->id);
    expect($adjustment->old_balance)->toBe('130.00');
    expect($adjustment->new_balance)->toBe('999.99');
});

test('user cannot adjust balance for someone elses account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($attacker)
        ->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 10,
        ])
        ->assertForbidden();
});

<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can update account name and opening balance and current balance changes by delta', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Old name',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 100,
        'current_balance' => 130,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('accounts.update', $account, absolute: false), [
            'name' => 'New name',
            'bank' => 'mbank',
            'type' => 'savings',
            'opening_balance' => 120,
        ]);

    $response->assertSessionHasNoErrors();

    $account->refresh();

    expect($account->name)->toBe('New name');
    expect($account->bank->value)->toBe('mbank');
    expect($account->type->value)->toBe('savings');
    expect($account->opening_balance)->toBe('120.00');
    expect($account->current_balance)->toBe('150.00'); // +20 delta
});

test('updating a cash account to a bank redirects to the accounts tab', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Gotówka',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account, absolute: false), [
            'name' => 'mBank',
            'bank' => 'mbank',
            'type' => 'checking',
            'opening_balance' => 0,
        ])
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));
});

test('updating a bank account to cash redirects to the wallets tab', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank',
        'bank' => 'mbank',
        'type' => 'checking',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account, absolute: false), [
            'name' => 'Gotówka',
            'bank' => 'cash',
            'type' => 'checking',
            'opening_balance' => 0,
        ])
        ->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
});

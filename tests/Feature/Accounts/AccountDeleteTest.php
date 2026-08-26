<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can soft delete account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To delete',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('accounts.destroy', $account, absolute: false));

    $response->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);
});

test('deleting a bank account redirects to the accounts tab', function () {
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
        ->delete(route('accounts.destroy', $account, absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);
});

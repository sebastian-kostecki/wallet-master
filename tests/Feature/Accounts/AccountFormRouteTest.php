<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guests are redirected to login from create', function () {
    $this->get(route('accounts.create', absolute: false))
        ->assertRedirect(route('login', absolute: false));
});

test('create redirects to the accounts index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.create', absolute: false))
        ->assertRedirect(route('accounts.index', absolute: false));
});

test('create forwards a valid kind query to the index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.create', ['kind' => 'wallets'], absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
});

test('create ignores an invalid kind query', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.create', ['kind' => 'nope'], absolute: false))
        ->assertRedirect(route('accounts.index', absolute: false));
});

test('owner edit redirects to the index tab for that account bank', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $cash = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $bank = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit', $cash, absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));

    $this->actingAs($user)
        ->get(route('accounts.edit', $bank, absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));
});

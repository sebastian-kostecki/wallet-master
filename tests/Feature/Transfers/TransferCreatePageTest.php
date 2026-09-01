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

test('guest is redirected to login', function () {
    $this->get(route('transfers.create', absolute: false))->assertRedirect(route('login', absolute: false));
});

test('create page redirects to the index with transfer=1', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('transfers.create', absolute: false))
        ->assertRedirect(route('transactions.index', ['transfer' => 1], absolute: false));
});

test('create page redirects to the index with remembered filters and transfer=1', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ], absolute: false))->assertOk();

    $this
        ->actingAs($user)
        ->get(route('transfers.create', absolute: false))
        ->assertRedirect(route('transactions.index', [
            'account_id' => $account->id,
            'from' => '01-04-2026',
            'to' => '30-04-2026',
            'transfer' => 1,
        ], absolute: false));
});

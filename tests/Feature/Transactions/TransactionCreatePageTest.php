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

test('create page redirects to the index with new=1', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($user)
        ->get(route('transactions.create', absolute: false))
        ->assertRedirect(route('transactions.index', ['new' => 1], absolute: false));
});

test('create page redirects to the index with remembered filters and new=1', function () {
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
        ->get(route('transactions.create', absolute: false))
        ->assertRedirect(route('transactions.index', [
            'account_id' => $account->id,
            'from' => '01-04-2026',
            'to' => '30-04-2026',
            'new' => 1,
        ], absolute: false));
});

<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guests are redirected to login', function () {
    $this->get('/accounts')->assertRedirect('/login');
});

test('users see only their own accounts', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $userA->id,
        'currency_id' => $plnId,
        'name' => 'Account A',
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    Account::query()->create([
        'user_id' => $userB->id,
        'currency_id' => $plnId,
        'name' => 'Account B',
        'opening_balance' => 200,
        'current_balance' => 200,
    ]);

    $response = $this->actingAs($userA)->get('/accounts');
    $response->assertOk();

    $response->assertSee('Account A');
    $response->assertDontSee('Account B');

    expect($accountA->fresh())->not->toBeNull();
});

test('soft deleted accounts are not listed', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $user = User::factory()->create();

    $activeAccount = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Active',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $deletedAccount = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Deleted',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $deletedAccount->delete();

    $response = $this->actingAs($user)->get('/accounts');

    $response->assertOk();
    $response->assertSee($activeAccount->name);
    $response->assertDontSee($deletedAccount->name);
});

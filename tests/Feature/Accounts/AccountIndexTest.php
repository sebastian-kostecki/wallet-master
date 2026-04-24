<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

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

    $cashAccount = Account::query()->create([
        'user_id' => $userA->id,
        'currency_id' => $plnId,
        'name' => 'Cash Account',
        'bank' => 'cash',
        'type' => 'ror',
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $mBankAccount = Account::query()->create([
        'user_id' => $userA->id,
        'currency_id' => $plnId,
        'name' => 'mBank Account',
        'bank' => 'mbank',
        'type' => 'ror',
        'opening_balance' => 50,
        'current_balance' => 50,
    ]);

    Account::query()->create([
        'user_id' => $userB->id,
        'currency_id' => $plnId,
        'name' => 'Account B',
        'bank' => 'cash',
        'type' => 'ror',
        'opening_balance' => 200,
        'current_balance' => 200,
    ]);

    $response = $this->actingAs($userA)->get('/accounts');
    $response->assertOk();

    $response->assertSee('Cash Account');
    $response->assertSee('mBank Account');
    $response->assertDontSee('Account B');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('accounts', 2)
        ->where('accounts.0.id', $cashAccount->id)
        ->where('accounts.0.bank_icon_url', null)
        ->where('accounts.1.id', $mBankAccount->id)
        ->where('accounts.1.bank_icon_url', asset('icons/banks/mbank.jpeg'))
    );

    expect($cashAccount->fresh())->not->toBeNull();
});

test('soft deleted accounts are not listed', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $user = User::factory()->create();

    $activeAccount = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Active',
        'bank' => 'cash',
        'type' => 'ror',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $deletedAccount = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Deleted',
        'bank' => 'cash',
        'type' => 'ror',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $deletedAccount->delete();

    $response = $this->actingAs($user)->get('/accounts');

    $response->assertOk();
    $response->assertSee($activeAccount->name);
    $response->assertDontSee($deletedAccount->name);
});

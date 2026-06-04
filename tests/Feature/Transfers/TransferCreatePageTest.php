<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guest is redirected to login', function () {
    $this->get('/transfers/create')->assertRedirect('/login');
});

test('page renders and includes active + deleted accounts', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $active = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Active',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $deleted = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Deleted',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $deleted->delete();

    $response = $this->actingAs($user)->get('/transfers/create');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transfers/Create', false)
        ->has('accounts', 2)
        ->where('accounts.0.id', $active->id)
        ->where('accounts.0.is_deleted', false)
        ->where('accounts.0.bank_icon_url', $active->bank_icon_url)
        ->where('accounts.1.id', $deleted->id)
        ->where('accounts.1.is_deleted', true)
        ->where('accounts.1.bank_icon_url', $deleted->bank_icon_url)
    );
});

test('transfer create page exposes savings account type as lowercase enum value', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Checking',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->get('/transfers/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transfers/Create', false)
            ->has('accounts', 2)
            ->where('accounts.0.type', 'checking')
            ->where('accounts.1.type', 'savings')
            ->where('accounts.0.id', $checking->id)
            ->where('accounts.1.id', $savings->id)
        );
});

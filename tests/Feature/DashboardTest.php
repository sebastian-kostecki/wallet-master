<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard', absolute: false));
    $response->assertRedirect(route('login', absolute: false));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', absolute: false));
    $response->assertStatus(200);
});

test('dashboard includes only the authenticated user accounts', function () {
    $this->seed(CurrencySeeder::class);
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $own = Account::query()->create([
        'user_id' => $userA->id,
        'currency_id' => $plnId,
        'name' => 'Cash A',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 10,
        'current_balance' => 10,
    ]);

    Account::query()->create([
        'user_id' => $userB->id,
        'currency_id' => $plnId,
        'name' => 'Cash B',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 99,
        'current_balance' => 99,
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.id', $own->id)
            ->where('accounts.0.bank', 'cash')
            ->where('accounts.0.type', 'checking')
            ->where('accounts.0.currency.code', 'PLN')
        );
});

<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('users cannot edit another users account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($otherUser)
        ->get("/accounts/{$account->id}/edit")
        ->assertForbidden();
});

test('users cannot update another users account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($otherUser)
        ->put("/accounts/{$account->id}", [
            'name' => 'Stolen name',
            'bank' => Bank::Cash->value,
            'type' => AccountType::Checking->value,
            'opening_balance' => 0,
        ])
        ->assertForbidden();
});

test('users cannot delete another users account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($otherUser)
        ->delete("/accounts/{$account->id}")
        ->assertForbidden();
});

test('users cannot adjust balance on another users account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($otherUser)
        ->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 500,
        ])
        ->assertForbidden();
});

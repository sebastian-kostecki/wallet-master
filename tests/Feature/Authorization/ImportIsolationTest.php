<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('users cannot view another users import', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'status' => 'draft',
    ]);

    $this->actingAs($otherUser)
        ->getJson(route('imports.show', $import))
        ->assertForbidden();
});

test('users cannot commit another users import', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'status' => 'draft',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
        ],
    ]);

    $this->actingAs($otherUser)
        ->post(route('imports.commit', $import))
        ->assertForbidden();
});

test('import index json only returns imports for the authenticated user', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $userA->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $accountB = Account::query()->create([
        'user_id' => $userB->id,
        'currency_id' => $plnId,
        'name' => 'B',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Import::query()->create([
        'user_id' => $userA->id,
        'account_id' => $accountA->id,
        'status' => 'draft',
    ]);

    Import::query()->create([
        'user_id' => $userB->id,
        'account_id' => $accountB->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($userA)
        ->getJson(route('imports.index'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.account_id'))->toBe($accountA->id);
});

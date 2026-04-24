<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create an account and current balance equals opening balance', function () {
    $user = User::factory()->create();
    DB::table('currencies')->updateOrInsert(
        ['code' => 'USD'],
        [
            'name' => 'US Dollar',
            'symbol' => '$',
            'precision' => 2,
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );
    $usdId = (int) Currency::query()->where('code', 'USD')->value('id');

    $response = $this
        ->actingAs($user)
        ->post('/accounts', [
            'name' => 'Konto testowe',
            'bank' => 'cash',
            'type' => 'ror',
            'currency_id' => $usdId,
            'opening_balance' => 123.45,
        ]);

    $response->assertSessionHasNoErrors();

    $account = Account::query()->where('user_id', $user->id)->first();

    expect($account)->not->toBeNull();
    expect($account->bank->value)->toBe('cash');
    expect($account->type->value)->toBe('ror');
    expect($account->currency_id)->toBe($usdId);
    expect($account->opening_balance)->toBe('123.45');
    expect($account->current_balance)->toBe('123.45');
});

test('account name is required', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $response = $this
        ->actingAs($user)
        ->post('/accounts', [
            'name' => '',
            'bank' => 'cash',
            'type' => 'ror',
            'currency_id' => $plnId,
            'opening_balance' => 0,
        ]);

    $response->assertSessionHasErrors('name');
});

test('account bank is required', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $response = $this
        ->actingAs($user)
        ->post('/accounts', [
            'name' => 'Konto testowe',
            'bank' => '',
            'type' => 'ror',
            'currency_id' => $plnId,
            'opening_balance' => 0,
        ]);

    $response->assertSessionHasErrors('bank');
});

test('account type is required', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $response = $this
        ->actingAs($user)
        ->post('/accounts', [
            'name' => 'Konto testowe',
            'bank' => 'cash',
            'type' => '',
            'currency_id' => $plnId,
            'opening_balance' => 0,
        ]);

    $response->assertSessionHasErrors('type');
});

<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    Route::bind('account', fn (string $value) => Account::withTrashed()->findOrFail($value));

    Route::middleware(['web', 'auth', EnsureAccountIsActive::class])->patch('/_test/accounts/{account}/guard', function (Account $account) {
        return response()->noContent();
    });
});

test('active account passes middleware', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Active',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->patch("/_test/accounts/{$account->id}/guard")
        ->assertNoContent();
});

test('deleted account is blocked by middleware', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Deleted',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $account->delete();

    $this->actingAs($user)
        ->patch("/_test/accounts/{$account->id}/guard")
        ->assertForbidden();
});

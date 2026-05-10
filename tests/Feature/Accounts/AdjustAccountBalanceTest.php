<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountBalanceAdjustment;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can set current balance and adjustment is audited', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 130,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 10, 12, 0, 0));

    $response = $this
        ->actingAs($user)
        ->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 999.99,
        ]);

    $response->assertSessionHasNoErrors();

    $account->refresh();
    expect($account->current_balance)->toBe('999.99');

    $adjustment = AccountBalanceAdjustment::query()->where('account_id', $account->id)->first();
    expect($adjustment)->not->toBeNull();
    expect($adjustment->user_id)->toBe($user->id);
    expect($adjustment->old_balance)->toBe('130.00');
    expect($adjustment->new_balance)->toBe('999.99');

    $txn = Transaction::query()
        ->where('account_id', $account->id)
        ->where('type', TransactionType::Adjustment)
        ->first();
    expect($txn)->not->toBeNull();
    expect((string) $txn->amount)->toBe('869.99');
    expect($txn->type)->toBe(TransactionType::Adjustment);
    expect((string) $txn->description)->toBe('Korekta salda');
});

test('setting balance to same value skips adjustment transaction and audit row', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 130,
    ]);

    $this
        ->actingAs($user)
        ->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 130,
        ])
        ->assertSessionHasNoErrors();

    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(0);
    expect(AccountBalanceAdjustment::query()->where('account_id', $account->id)->count())->toBe(0);
});

test('balance adjustment appears on transactions index props', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 10, 12, 0, 0));

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $this->actingAs($user)->patch("/accounts/{$account->id}/balance", [
        'new_balance' => 150,
    ])->assertSessionHasNoErrors();

    $adjustmentId = Transaction::query()->where('type', TransactionType::Adjustment)->value('id');
    expect($adjustmentId)->not->toBeNull();

    $this->actingAs($user)->get(route('transactions.index', [
        'all_time' => 1,
    ]))->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->has('transactions.data', 1)
        ->where('transactions.data.0.id', $adjustmentId)
        ->where('transactions.data.0.type', 'adjustment')
    );
});

test('user cannot adjust balance for someone elses account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($attacker)
        ->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 10,
        ])
        ->assertForbidden();
});

<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guest is redirected to login', function () {
    $this->post('/transactions', [])->assertRedirect('/login');
});

test('user can create a transaction and it updates account balance', function () {
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

    $response = $this
        ->actingAs($user)
        ->post('/transactions', [
            'account_id' => $account->id,
            'date' => '24-04-2026',
            'amount' => -12.34,
            'description' => 'Coffee',
            'subject' => 'Cafe',
        ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::query()->where('user_id', $user->id)->first();
    expect($transaction)->not->toBeNull();
    expect((int) $transaction->account_id)->toBe($account->id);
    expect((string) $transaction->amount)->toBe('-12.34');
    expect((string) $transaction->type)->toBe('expense');
    expect($transaction->booked_at->toDateString())->toBe('2026-04-24');

    $account->refresh();
    expect($account->current_balance)->toBe('87.66');
});

test('user can explicitly set booked_at when creating a transaction', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $response = $this
        ->actingAs($user)
        ->post('/transactions', [
            'account_id' => $account->id,
            'date' => '24-04-2026',
            'booked_at' => '30-03-2026',
            'amount' => -12.34,
            'description' => 'Coffee',
        ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::query()->where('user_id', $user->id)->firstOrFail();
    expect($transaction->date->toDateString())->toBe('2026-04-24');
    expect($transaction->booked_at->toDateString())->toBe('2026-03-30');
});

test('amount cannot be zero', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($user)
        ->post('/transactions', [
            'account_id' => $account->id,
            'date' => '24-04-2026',
            'amount' => 0,
            'description' => 'Zero',
        ])
        ->assertSessionHasErrors('amount');
});

test('cannot create a transaction for a deleted account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

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

    $this
        ->actingAs($user)
        ->post('/transactions', [
            'account_id' => $account->id,
            'date' => '24-04-2026',
            'amount' => 10,
            'description' => 'Income',
        ])
        ->assertSessionHasErrors('account_id');
});

<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('creating account records account_created telemetry event', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $logged = captureTelemetryLogs(function () use ($user, $plnId): void {
        $this->actingAs($user)->post('/accounts', [
            'name' => 'Konto testowe',
            'bank' => 'cash',
            'type' => 'checking',
            'currency_id' => $plnId,
            'opening_balance' => 100,
        ])->assertSessionHasNoErrors();
    });

    $account = Account::query()->where('user_id', $user->id)->first();
    expect($account)->not->toBeNull();

    assertTelemetryEvent($logged, 'account_created', function (array $context) use ($user, $account) {
        return $context['account_id'] === $account->id && $context['user_id'] === $user->id;
    });
});

test('updating account records account_updated telemetry event', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Old name',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $logged = captureTelemetryLogs(function () use ($user, $account): void {
        $this->actingAs($user)->patch("/accounts/{$account->id}", [
            'name' => 'New name',
            'bank' => 'mbank',
            'type' => 'savings',
            'opening_balance' => 120,
        ])->assertSessionHasNoErrors();
    });

    assertTelemetryEvent($logged, 'account_updated', function (array $context) use ($user, $account) {
        return $context['account_id'] === $account->id && $context['user_id'] === $user->id;
    });
});

test('deleting account records account_deleted and account_deleted_with_transactions telemetry events', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To delete',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-01-01',
        'booked_at' => '2026-01-01',
        'amount' => '10.00',
        'type' => TransactionType::Expense,
        'description' => 'Test',
        'normalized_description' => 'test',
        'dedupe_hash' => random_bytes(16),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-01-02',
        'booked_at' => '2026-01-02',
        'amount' => '20.00',
        'type' => TransactionType::Income,
        'description' => 'Test 2',
        'normalized_description' => 'test 2',
        'dedupe_hash' => random_bytes(16),
    ]);

    $logged = captureTelemetryLogs(function () use ($user, $account): void {
        $this->actingAs($user)->delete("/accounts/{$account->id}")->assertRedirect('/accounts');
    });

    assertTelemetryEvent($logged, 'account_deleted', function (array $context) use ($user, $account) {
        return $context['account_id'] === $account->id && $context['user_id'] === $user->id;
    });
    assertTelemetryEvent($logged, 'account_deleted_with_transactions', function (array $context) use ($account) {
        return $context['account_id'] === $account->id && $context['transaction_count'] === 2;
    });
});

test('adjusting account balance records account_balance_adjusted telemetry event with decimal strings', function () {
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

    $logged = captureTelemetryLogs(function () use ($user, $account): void {
        $this->actingAs($user)->patch("/accounts/{$account->id}/balance", [
            'new_balance' => 999.99,
        ])->assertSessionHasNoErrors();
    });

    assertTelemetryEvent($logged, 'account_balance_adjusted', function (array $context) use ($user, $account) {
        return $context['account_id'] === $account->id
            && $context['old_balance'] === '130.00'
            && $context['new_balance'] === '999.99'
            && $context['user_id'] === $user->id
            && ! array_key_exists('name', $context);
    });
});

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

test('creating transaction records transaction_created telemetry event', function () {
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

    $categoryId = defaultCategoryId($user);

    $logged = captureTelemetryLogs(function () use ($user, $account, $categoryId): void {
        $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'date' => '24-04-2026',
            'amount' => -12.34,
            'description' => 'Coffee',
            'category_id' => $categoryId,
        ])->assertSessionHasNoErrors();
    });

    $transaction = Transaction::query()->where('user_id', $user->id)->first();
    expect($transaction)->not->toBeNull();

    assertTelemetryEvent($logged, 'transaction_created', function (array $context) use ($user, $transaction, $account) {
        return $context['transaction_id'] === $transaction->id
            && $context['account_id'] === $account->id
            && $context['user_id'] === $user->id;
    });
});

test('updating transaction records transaction_updated telemetry event', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 90,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Old',
        'subject' => null,
        'normalized_description' => 'old',
        'dedupe_hash' => md5('2026-04-20|-10.00|old', true),
    ]);

    $logged = captureTelemetryLogs(function () use ($user, $transaction, $account): void {
        $this->actingAs($user)->put("/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => -20,
            'description' => 'New',
            'subject' => null,
            'category_id' => $transaction->category_id,
        ])->assertSessionHasNoErrors();
    });

    assertTelemetryEvent($logged, 'transaction_updated', function (array $context) use ($user, $transaction, $account) {
        return $context['transaction_id'] === $transaction->id
            && $context['account_id'] === $account->id
            && $context['user_id'] === $user->id;
    });
});

test('deleting transaction records transaction_deleted telemetry event', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 80,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -20,
        'type' => 'expense',
        'description' => 'Expense',
        'subject' => null,
        'normalized_description' => 'expense',
        'dedupe_hash' => md5('2026-04-20|-20.00|expense', true),
    ]);

    $logged = captureTelemetryLogs(function () use ($user, $transaction): void {
        $this->actingAs($user)->delete("/transactions/{$transaction->id}")->assertRedirect('/transactions');
    });

    assertTelemetryEvent($logged, 'transaction_deleted', function (array $context) use ($user, $transaction, $account) {
        return $context['transaction_id'] === $transaction->id
            && $context['account_id'] === $account->id
            && $context['user_id'] === $user->id;
    });
});

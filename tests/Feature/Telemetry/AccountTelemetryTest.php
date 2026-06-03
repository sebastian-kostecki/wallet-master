<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('creating account records account_created telemetry event', function () {
    Log::fake();

    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post('/accounts', [
        'name' => 'Konto testowe',
        'bank' => 'cash',
        'type' => 'checking',
        'currency_id' => $plnId,
        'opening_balance' => 100,
    ])->assertSessionHasNoErrors();

    $account = Account::query()->where('user_id', $user->id)->first();
    expect($account)->not->toBeNull();

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user, $account) {
        return $message === 'account_created'
            && $context['event'] === 'account_created'
            && $context['account_id'] === $account->id
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });
});

test('updating account records account_updated telemetry event', function () {
    Log::fake();

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

    $this->actingAs($user)->patch("/accounts/{$account->id}", [
        'name' => 'New name',
        'bank' => 'mbank',
        'type' => 'savings',
        'opening_balance' => 120,
    ])->assertSessionHasNoErrors();

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user, $account) {
        return $message === 'account_updated'
            && $context['event'] === 'account_updated'
            && $context['account_id'] === $account->id
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });
});

test('deleting account records account_deleted and account_deleted_with_transactions telemetry events', function () {
    Log::fake();

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

    $this->actingAs($user)->delete("/accounts/{$account->id}")->assertRedirect('/accounts');

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user, $account) {
        return $message === 'account_deleted'
            && $context['event'] === 'account_deleted'
            && $context['account_id'] === $account->id
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user, $account) {
        return $message === 'account_deleted_with_transactions'
            && $context['event'] === 'account_deleted_with_transactions'
            && $context['account_id'] === $account->id
            && $context['transaction_count'] === 2
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });
});

test('adjusting account balance records account_balance_adjusted telemetry event with decimal strings', function () {
    Log::fake();

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

    $this->actingAs($user)->patch("/accounts/{$account->id}/balance", [
        'new_balance' => 999.99,
    ])->assertSessionHasNoErrors();

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user, $account) {
        return $message === 'account_balance_adjusted'
            && $context['event'] === 'account_balance_adjusted'
            && $context['account_id'] === $account->id
            && $context['old_balance'] === '130.00'
            && $context['new_balance'] === '999.99'
            && $context['user_id'] === $user->id
            && ! array_key_exists('name', $context)
            && isset($context['recorded_at']);
    });
});

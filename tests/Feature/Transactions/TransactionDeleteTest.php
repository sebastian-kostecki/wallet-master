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

test('deleting a transaction reverses its effect on balance', function () {
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

    $this
        ->actingAs($user)
        ->delete("/transactions/{$transaction->id}")
        ->assertRedirect('/transactions');

    $account->refresh();
    expect($account->current_balance)->toBe('100.00');
});

test('cannot delete a transaction on a deleted account', function () {
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

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => 10,
        'type' => 'income',
        'description' => 'Income',
        'subject' => null,
        'normalized_description' => 'income',
        'dedupe_hash' => md5('2026-04-20|10.00|income', true),
    ]);

    $account->delete();

    $this
        ->actingAs($user)
        ->delete("/transactions/{$transaction->id}")
        ->assertForbidden();
});

test('deleting one side of a transfer deletes both and reverts both balances', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 80,
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 20,
    ]);

    $transferId = 'test-transfer-id';

    $withdrawal = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $from->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -20,
        'type' => 'expense',
        'description' => 'Transfer to To',
        'subject' => null,
        'normalized_description' => 'transfer to to',
        'dedupe_hash' => md5($transferId.'|withdrawal', true),
        'transfer_id' => $transferId,
    ]);

    $deposit = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $to->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => 20,
        'type' => 'income',
        'description' => 'Transfer from From',
        'subject' => null,
        'normalized_description' => 'transfer from from',
        'dedupe_hash' => md5($transferId.'|deposit', true),
        'transfer_id' => $transferId,
    ]);

    $this
        ->actingAs($user)
        ->delete("/transactions/{$withdrawal->id}")
        ->assertRedirect('/transactions');

    expect(Transaction::query()->whereKey($withdrawal->id)->exists())->toBeFalse();
    expect(Transaction::query()->whereKey($deposit->id)->exists())->toBeFalse();

    $from->refresh();
    $to->refresh();

    expect($from->current_balance)->toBe('100.00');
    expect($to->current_balance)->toBe('0.00');
});

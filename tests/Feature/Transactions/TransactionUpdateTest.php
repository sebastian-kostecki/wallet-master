<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Tests\Support\FakeDescriptionMemoryRepository;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('updating a transaction updates balance by delta', function () {
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

    $this
        ->actingAs($user)
        ->put(route('transactions.update', $transaction, absolute: false), [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => -20,
            'description' => 'New',
            'subject' => null,
            'category_id' => $transaction->category_id,
        ])
        ->assertSessionHasNoErrors();

    $account->refresh();
    expect($account->current_balance)->toBe('80.00');
});

test('cannot update a transaction on a deleted account', function () {
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
        ->put(route('transactions.update', $transaction, absolute: false), [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => 20,
            'description' => 'Income',
            'category_id' => $transaction->category_id,
        ])
        ->assertForbidden();
});

test('updates an imported transaction and remembers user corrections (best-effort)', function () {
    $fakeRepo = new FakeDescriptionMemoryRepository;
    app()->instance(DescriptionMemoryRepository::class, $fakeRepo);

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Imported',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'committed',
        'mapping' => [],
        'details' => [],
        'rows_total' => 0,
        'rows_imported' => 0,
        'rows_skipped_duplicate' => 0,
        'rows_failed_validation' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'import_id' => $import->id,
        'raw_statement_description' => 'ATM CASH OUT',
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'ATM CASH OUT',
        'subject' => null,
        'normalized_description' => 'atm cash out',
        'dedupe_hash' => md5('2026-04-20|-10.00|atm cash out', true),
    ]);

    $this
        ->actingAs($user)
        ->put(route('transactions.update', $transaction, absolute: false), [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => -10,
            'description' => 'Cash withdrawal',
            'subject' => 'ATM',
            'category_id' => $transaction->category_id,
        ])
        ->assertSessionHasNoErrors();

    expect($fakeRepo->rememberCalls)->toHaveCount(1);
    expect($fakeRepo->rememberCalls[0]['user_id'])->toBe($user->id);
    expect($fakeRepo->rememberCalls[0]['bank'])->toBe(Bank::BnpParibas);
    expect($fakeRepo->rememberCalls[0]['raw'])->toBe('ATM CASH OUT');
    expect($fakeRepo->rememberCalls[0]['subject'])->toBe('ATM');
    expect($fakeRepo->rememberCalls[0]['description'])->toBe('Cash withdrawal');
});

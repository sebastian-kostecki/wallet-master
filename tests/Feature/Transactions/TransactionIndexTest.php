<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guests are redirected to login', function () {
    $this->get('/transactions')->assertRedirect('/login');
});

test('users can filter by account and date range, sort, and see summary', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'B',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'amount' => 100,
        'type' => 'income',
        'description' => 'Salary',
        'subject' => null,
        'normalized_description' => 'salary',
        'dedupe_hash' => md5('2026-04-10|100.00|salary', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'currency_id' => $plnId,
        'date' => '2026-04-11',
        'amount' => -25,
        'type' => 'expense',
        'description' => 'Groceries',
        'subject' => null,
        'normalized_description' => 'groceries',
        'dedupe_hash' => md5('2026-04-11|-25.00|groceries', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-11',
        'amount' => -999,
        'type' => 'expense',
        'description' => 'Other account',
        'subject' => null,
        'normalized_description' => 'other account',
        'dedupe_hash' => md5('2026-04-11|-999.00|other account', true),
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/transactions?account_id='.$accountA->id.'&from=10-04-2026&to=11-04-2026&sort=amount&direction=asc');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('component', 'transactions/Index')
        ->where('filters.account_id', $accountA->id)
        ->where('filters.from', '10-04-2026')
        ->where('filters.to', '11-04-2026')
        ->where('filters.sort', 'amount')
        ->where('filters.direction', 'asc')
        ->has('transactions.data', 2)
        ->where('summary.total_income', '100.00')
        ->where('summary.total_expense', '25.00')
    );
});

test('date range is validated', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/transactions?from=20-04-2026&to=10-04-2026')
        ->assertSessionHasErrors('from');
});


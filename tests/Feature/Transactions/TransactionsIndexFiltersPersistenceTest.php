<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionsIndexQuery;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('index visit remembers filters in session', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
    ]))->assertOk();

    expect(session(TransactionsIndexQuery::sessionKey()))->toMatchArray([
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
    ]);
});

test('store redirects to index with remembered filters', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))->assertOk();

    $this->actingAs($user)
        ->post(route('transactions.store', absolute: false), [
            'account_id' => $account->id,
            'date' => '10-04-2026',
            'amount' => -10,
            'description' => 'Test',
            'category_id' => defaultCategoryId($user),
        ])
        ->assertRedirect(route('transactions.index', [
            'account_id' => $account->id,
            'from' => '01-04-2026',
            'to' => '30-04-2026',
        ]));
});

test('destroy redirects to index with remembered filters', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 80,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => -20,
        'type' => 'expense',
        'description' => 'Expense',
        'subject' => null,
        'normalized_description' => 'expense',
        'dedupe_hash' => md5('x', true),
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))->assertOk();

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction, absolute: false))
        ->assertRedirect(route('transactions.index', [
            'from' => '01-04-2026',
            'to' => '30-04-2026',
        ]));
});

test('clearing filters updates session to sort-only state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('transactions.index', [
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))->assertOk();

    $this->actingAs($user)->get(route('transactions.index', [
        'sort' => 'date',
        'direction' => 'desc',
    ]))->assertOk();

    expect(session(TransactionsIndexQuery::sessionKey()))->toBe([
        'sort' => 'date',
        'direction' => 'desc',
    ]);
});

test('inertia shares transactionsIndexSearch from session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('transactions.index', [
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactionsIndexSearch', '?from=01-04-2026&to=30-04-2026')
        );
});

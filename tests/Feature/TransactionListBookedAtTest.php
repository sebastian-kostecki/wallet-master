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

test('transactions are filtered by booked_at when set, otherwise by date', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $bookedInMarch = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-03-30',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Late booking',
        'subject' => null,
        'normalized_description' => 'late booking',
        'dedupe_hash' => md5('2026-04-10|-10.00|late booking', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => -5,
        'type' => 'expense',
        'description' => 'April tx',
        'subject' => null,
        'normalized_description' => 'april tx',
        'dedupe_hash' => md5('2026-04-10|-5.00|april tx', true),
    ]);

    $responseMarch = $this
        ->actingAs($user)
        ->get('/transactions?account_id='.$account->id.'&from=01-03-2026&to=31-03-2026');

    $responseMarch->assertOk();
    $responseMarch->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->has('transactions.data', 1)
        ->where('transactions.data.0.id', $bookedInMarch->id)
    );

    $responseApril = $this
        ->actingAs($user)
        ->get('/transactions?account_id='.$account->id.'&from=01-04-2026&to=30-04-2026');

    $responseApril->assertOk();
    $responseApril->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->has('transactions.data', 1)
        ->where('transactions.data.0.description', 'April tx')
    );
});

test('transactions fall back to date when booked_at is null', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $legacyTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-03-15',
        'booked_at' => null,
        'amount' => -1,
        'type' => 'expense',
        'description' => 'Legacy row',
        'subject' => null,
        'normalized_description' => 'legacy row',
        'dedupe_hash' => md5('2026-03-15|-1.00|legacy row', true),
    ]);

    $this
        ->actingAs($user)
        ->get('/transactions?account_id='.$account->id.'&from=01-03-2026&to=31-03-2026')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $legacyTransaction->id)
        );
});

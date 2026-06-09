<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('transaction summary excludes internal transfers from income and expense totals', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::MBank,
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
        'booked_at' => '2026-04-10',
        'amount' => 100,
        'type' => 'income',
        'description' => 'Salary',
        'subject' => null,
        'normalized_description' => 'salary',
        'dedupe_hash' => md5('2026-04-10|100.00|salary', true),
    ]);

    $transferId = (string) Str::uuid();

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'currency_id' => $plnId,
        'date' => '2026-04-11',
        'booked_at' => '2026-04-11',
        'amount' => -200,
        'type' => 'transfer',
        'description' => 'Transfer out',
        'subject' => null,
        'transfer_id' => $transferId,
        'normalized_description' => 'transfer out',
        'dedupe_hash' => md5($transferId.'|out', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-11',
        'booked_at' => '2026-04-11',
        'amount' => 200,
        'type' => 'transfer',
        'description' => 'Transfer in',
        'subject' => null,
        'transfer_id' => $transferId,
        'normalized_description' => 'transfer in',
        'dedupe_hash' => md5($transferId.'|in', true),
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', ['from' => '10-04-2026', 'to' => '11-04-2026'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->has('transactions.data', 3)
            ->where('summary.total_income', 100)
            ->where('summary.total_expense', 0)
        );
});

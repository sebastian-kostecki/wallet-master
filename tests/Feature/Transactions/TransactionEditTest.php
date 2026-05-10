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

test('edit page includes account bank_icon_url', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::MBank,
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
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Coffee',
        'subject' => null,
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('2026-04-20|-10.00|coffee', true),
    ]);

    $response = $this
        ->actingAs($user)
        ->get("/transactions/{$transaction->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Edit', false)
        ->where('transaction.id', $transaction->id)
        ->has('accounts', 1)
        ->where('accounts.0.id', $account->id)
        ->where('accounts.0.bank', 'mbank')
        ->where('accounts.0.bank_icon_url', fn (mixed $value) => is_string($value) && str_contains($value, '/icons/banks/'))
    );
});

<?php

declare(strict_types=1);

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

test('users cannot create a transaction on another users account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($otherUser)
        ->post(route('transactions.store', absolute: false), [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => -10,
            'description' => 'Cross-user create',
        ])
        ->assertSessionHasErrors('account_id');
});

test('users do not see another users transactions on index', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Private expense',
        'subject' => null,
        'normalized_description' => 'private expense',
        'dedupe_hash' => md5('2026-04-20|-10.00|private expense', true),
    ]);

    $this->actingAs($otherUser)
        ->get(route('transactions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->where('transactions.meta.total', 0)
            ->has('transactions.data', 0)
        );
});

test('users cannot view another users transaction edit page', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $owner->id,
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

    $this->actingAs($otherUser)
        ->get(route('transactions.edit', $transaction, absolute: false))
        ->assertForbidden();
});

test('users cannot update another users transaction', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $owner->id,
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

    $this->actingAs($otherUser)
        ->put(route('transactions.update', $transaction, absolute: false), [
            'account_id' => $account->id,
            'date' => '20-04-2026',
            'amount' => -20,
            'description' => 'Stolen edit',
            'category_id' => defaultCategoryId($otherUser),
        ])
        ->assertForbidden();
});

test('users cannot delete another users transaction', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $owner->id,
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

    $this->actingAs($otherUser)
        ->delete(route('transactions.destroy', $transaction, absolute: false))
        ->assertForbidden();
});

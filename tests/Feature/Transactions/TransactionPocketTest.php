<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Pocket;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('expense can optionally reference a pocket', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $categoryId = defaultCategoryId($user);
    $pocket = Pocket::factory()->create(['user_id' => $user->id]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'date' => '01-03-2026',
        'amount' => '-25.00',
        'description' => 'Hotel deposit',
        'category_id' => $categoryId,
        'pocket_id' => $pocket->id,
    ])->assertSessionHasNoErrors();

    $transaction = Transaction::query()->where('user_id', $user->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->pocket_id)->toBe($pocket->id);
});

test('pocket_id must belong to user', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $other = User::factory()->create();
    $categoryId = defaultCategoryId($user);
    $otherPocket = Pocket::factory()->create(['user_id' => $other->id]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'date' => '01-03-2026',
        'amount' => '-25.00',
        'description' => 'Hotel deposit',
        'category_id' => $categoryId,
        'pocket_id' => $otherPocket->id,
    ])->assertSessionHasErrors('pocket_id');
});

test('user can update transaction pocket_id', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $categoryId = defaultCategoryId($user);
    $pocket = Pocket::factory()->create(['user_id' => $user->id]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $categoryId,
        'date' => '2026-03-01',
        'booked_at' => '2026-03-01',
        'amount' => '-10.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('update-goal', true),
    ]);

    $this->actingAs($user)->patch(route('transactions.update', $transaction), [
        'account_id' => $account->id,
        'date' => '01-03-2026',
        'amount' => '-10.00',
        'description' => 'Coffee',
        'category_id' => $categoryId,
        'pocket_id' => $pocket->id,
    ])->assertSessionHasNoErrors();

    expect($transaction->fresh()->pocket_id)->toBe($pocket->id);
});

test('transaction create page includes pockets list', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $response = $this->actingAs($user)->get(route('transactions.create'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Create', false)
        ->has('pockets', 1)
        ->where('pockets.0.id', $pocket->id)
        ->where('pockets.0.name', 'Wakacje')
    );
});

test('transaction edit page includes pockets list', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $categoryId = defaultCategoryId($user);
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $categoryId,
        'date' => '2026-03-01',
        'booked_at' => '2026-03-01',
        'amount' => '-10.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('edit-goals', true),
    ]);

    $response = $this->actingAs($user)->get(route('transactions.edit', $transaction));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Edit', false)
        ->has('pockets', 1)
        ->where('pockets.0.id', $pocket->id)
        ->where('pockets.0.name', 'Wakacje')
    );
});

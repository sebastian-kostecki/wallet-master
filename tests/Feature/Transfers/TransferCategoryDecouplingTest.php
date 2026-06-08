<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(fn () => $this->seed(CurrencySeeder::class));

test('transfer create persists null category_id on both legs', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->post(route('transfers.store', absolute: false), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'date' => '04-06-2026',
        'amount' => 10,
    ])->assertSessionHasNoErrors();

    $legs = Transaction::query()->where('user_id', $user->id)->whereNotNull('transfer_id')->get();

    expect($legs)->toHaveCount(2);
    expect($legs->every(fn ($t) => $t->category_id === null))->toBeTrue();
    expect($legs->every(fn ($t) => $t->type === TransactionType::Transfer))->toBeTrue();
});

test('transfer create rejects category_id in payload', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $categoryId = defaultCategoryId($user);

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->post(route('transfers.store', absolute: false), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'date' => '04-06-2026',
        'amount' => 10,
        'category_id' => $categoryId,
    ])->assertSessionHasErrors('category_id');
});

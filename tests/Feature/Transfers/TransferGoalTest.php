<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('transfer to savings account requires goal_id', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Checking',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 1000,
        'current_balance' => 1000,
    ]);

    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'date' => '01-03-2026',
        'amount' => '200',
    ])->assertSessionHasErrors('goal_id');
});

test('transfer from savings account requires goal_id', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Checking',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $savings->id,
        'to_account_id' => $checking->id,
        'date' => '01-03-2026',
        'amount' => '150',
    ])->assertSessionHasErrors('goal_id');
});

test('transfer between checking accounts prohibits goal_id', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

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

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'date' => '01-03-2026',
        'amount' => '50',
        'goal_id' => $goal->id,
    ])->assertSessionHasErrors('goal_id');
});

test('transfer persists same goal_id on both legs with null category', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $goal = Goal::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Checking',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 1000,
        'current_balance' => 1000,
    ]);

    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->post(route('transfers.store'), [
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'date' => '01-03-2026',
        'amount' => '200',
        'goal_id' => $goal->id,
    ])->assertSessionHasNoErrors();

    $transferId = Transaction::query()
        ->where('user_id', $user->id)
        ->whereNotNull('transfer_id')
        ->value('transfer_id');

    expect($transferId)->not->toBeNull();

    $withdraw = Transaction::query()
        ->where('transfer_id', $transferId)
        ->where('amount', '<', 0)
        ->first();

    $deposit = Transaction::query()
        ->where('transfer_id', $transferId)
        ->where('amount', '>', 0)
        ->first();

    expect($withdraw)->not->toBeNull();
    expect($deposit)->not->toBeNull();
    expect($withdraw->goal_id)->toBe($goal->id);
    expect($deposit->goal_id)->toBe($goal->id);
    expect($withdraw->category_id)->toBeNull();
    expect($deposit->category_id)->toBeNull();
});

test('transfer create page includes goals list', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $response = $this->actingAs($user)->get(route('transfers.create'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transfers/Create', false)
        ->has('goals', 1)
        ->where('goals.0.id', $goal->id)
        ->where('goals.0.name', 'Wakacje')
        ->missing('categories')
        ->missing('default_category_id')
    );
});

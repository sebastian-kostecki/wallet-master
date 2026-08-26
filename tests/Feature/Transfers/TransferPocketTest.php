<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\PocketMovement;
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

test('savings transfer without pocket succeeds', function () {
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
    expect($withdraw->pocket_id)->toBeNull();
    expect($deposit->pocket_id)->toBeNull();
    expect($withdraw->pocket_movement)->toBeNull();
    expect($deposit->pocket_movement)->toBeNull();
});

test('checking-to-checking transfer with pocket and deposit persists both legs', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id]);

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
        'pocket_id' => $pocket->id,
        'pocket_movement' => 'deposit',
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
    expect($withdraw->pocket_id)->toBe($pocket->id);
    expect($deposit->pocket_id)->toBe($pocket->id);
    expect($withdraw->pocket_movement)->toBe(PocketMovement::Deposit);
    expect($deposit->pocket_movement)->toBe(PocketMovement::Deposit);
});

test('transfer with pocket_id without pocket_movement fails', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $pocket = Pocket::factory()->create(['user_id' => $user->id]);

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
        'pocket_id' => $pocket->id,
    ])->assertSessionHasErrors('pocket_movement');
});

test('transfer persists same pocket_id on both legs with null category', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

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
        'pocket_id' => $pocket->id,
        'pocket_movement' => 'deposit',
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
    expect($withdraw->pocket_id)->toBe($pocket->id);
    expect($deposit->pocket_id)->toBe($pocket->id);
    expect($withdraw->pocket_movement)->toBe(PocketMovement::Deposit);
    expect($deposit->pocket_movement)->toBe(PocketMovement::Deposit);
    expect($withdraw->category_id)->toBeNull();
    expect($deposit->category_id)->toBeNull();
});

test('transfer rejects pocket when pocket currency does not match savings account currency', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    Currency::query()->create([
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'precision' => 2,
    ]);
    $eurId = (int) Currency::query()->where('code', 'EUR')->value('id');

    $user = User::factory()->create();
    ensureUserCategories($user);
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'currency_id' => $eurId]);

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
        'pocket_id' => $pocket->id,
        'pocket_movement' => 'deposit',
    ])->assertSessionHasErrors('pocket_id');
});

test('transfer create page includes pockets list', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $response = $this->actingAs($user)->get(route('transfers.create'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transfers/Create', false)
        ->has('pockets', 1)
        ->where('pockets.0.id', $pocket->id)
        ->where('pockets.0.name', 'Wakacje')
        ->missing('categories')
        ->missing('default_category_id')
    );
});

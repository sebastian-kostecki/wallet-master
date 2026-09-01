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

test('expense rejects pocket_id', function () {
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
    ])->assertSessionHasErrors('pocket_id');
});

test('expense update rejects pocket_id', function () {
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
        'type' => 'expense',
        'description' => 'Coffee',
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('update-pocket', true),
    ]);

    $this->actingAs($user)->patch(route('transactions.update', $transaction), [
        'account_id' => $account->id,
        'date' => '01-03-2026',
        'amount' => '-10.00',
        'description' => 'Coffee',
        'category_id' => $categoryId,
        'pocket_id' => $pocket->id,
    ])->assertSessionHasErrors('pocket_id');
});

test('transaction index includes pockets list', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $response = $this->actingAs($user)->get(route('transactions.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->has('pockets', 1)
        ->where('pockets.0.id', $pocket->id)
        ->where('pockets.0.name', 'Wakacje')
        ->where('pockets.0.icon', $pocket->icon)
        ->where('pockets.0.color', $pocket->color)
    );
});

test('linked transfer update writes pocket and movement on both legs', function () {
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

    expect($withdraw)->not->toBeNull();

    $this->actingAs($user)->patch(route('transactions.update', $withdraw), [
        'account_id' => $from->id,
        'date' => '01-03-2026',
        'amount' => '-50.00',
        'description' => $withdraw->description,
        'pocket_id' => $pocket->id,
        'pocket_movement' => 'withdrawal',
    ])->assertSessionHasNoErrors();

    $legs = Transaction::query()->where('transfer_id', $transferId)->get();

    expect($legs)->toHaveCount(2);
    foreach ($legs as $leg) {
        expect($leg->pocket_id)->toBe($pocket->id);
        expect($leg->pocket_movement)->toBe(PocketMovement::Withdrawal);
    }
});

test('linked transfer update clearing pocket nulls both legs', function () {
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

    $withdraw = Transaction::query()
        ->where('transfer_id', $transferId)
        ->where('amount', '<', 0)
        ->first();

    $this->actingAs($user)->patch(route('transactions.update', $withdraw), [
        'account_id' => $from->id,
        'date' => '01-03-2026',
        'amount' => '-50.00',
        'description' => $withdraw->description,
        'pocket_id' => null,
    ])->assertSessionHasNoErrors();

    $legs = Transaction::query()->where('transfer_id', $transferId)->get();

    expect($legs)->toHaveCount(2);
    foreach ($legs as $leg) {
        expect($leg->pocket_id)->toBeNull();
        expect($leg->pocket_movement)->toBeNull();
    }
});

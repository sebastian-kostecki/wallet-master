<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Events\TransferCreated;
use App\Events\TransferFailedValidation;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guest is redirected to login', function () {
    $this->post(route('transfers.store', absolute: false), [])->assertRedirect(route('login', absolute: false));
});

test('user can create a transfer and it creates two linked transactions and updates balances', function () {
    Event::fake([TransferCreated::class]);

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
        'opening_balance' => 50,
        'current_balance' => 50,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('transfers.store', absolute: false), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'date' => '24-04-2026',
            'amount' => 12.34,
            'description' => 'Move money',
        ]);

    $response->assertSessionHasNoErrors();

    $transactions = Transaction::query()
        ->where('user_id', $user->id)
        ->whereNotNull('transfer_id')
        ->orderBy('id')
        ->get();

    expect($transactions)->toHaveCount(2);
    expect($transactions[0]->transfer_id)->toBe($transactions[1]->transfer_id);
    expect($transactions[0]->date->toDateString())->toBe('2026-04-24');
    expect($transactions[1]->date->toDateString())->toBe('2026-04-24');

    $withdrawal = $transactions->firstWhere('account_id', $from->id);
    $deposit = $transactions->firstWhere('account_id', $to->id);

    expect($withdrawal)->not->toBeNull();
    expect($deposit)->not->toBeNull();
    expect((string) $withdrawal->amount)->toBe('-12.34');
    expect((string) $deposit->amount)->toBe('12.34');
    expect($withdrawal->category_id)->toBeNull();
    expect($deposit->category_id)->toBeNull();

    $from->refresh();
    $to->refresh();

    expect((string) $from->current_balance)->toBe('87.66');
    expect((string) $to->current_balance)->toBe('62.34');

    Event::assertDispatched(TransferCreated::class);
});

test('user can create a transfer with optional subject on both legs', function () {
    Event::fake([TransferCreated::class]);

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
        'opening_balance' => 50,
        'current_balance' => 50,
    ]);

    $this
        ->actingAs($user)
        ->post(route('transfers.store', absolute: false), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'date' => '24-04-2026',
            'amount' => 5,
            'subject' => 'Internal move',
            'description' => 'Move money',
        ])
        ->assertSessionHasNoErrors();

    $transactions = Transaction::query()
        ->where('user_id', $user->id)
        ->whereNotNull('transfer_id')
        ->get();

    expect($transactions)->toHaveCount(2);
    expect($transactions->pluck('subject')->unique()->all())->toBe(['Internal move']);
});

test('cannot create a transfer to the same account', function () {
    Event::fake([TransferFailedValidation::class]);

    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($user)
        ->post(route('transfers.store', absolute: false), [
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'date' => '24-04-2026',
            'amount' => 10,
            'description' => 'Invalid',
        ])
        ->assertSessionHasErrors('to_account_id');

    expect(Transaction::query()->whereNotNull('transfer_id')->count())->toBe(0);
    Event::assertDispatched(TransferFailedValidation::class);
});

test('amount must be greater than zero', function () {
    Event::fake([TransferFailedValidation::class]);

    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
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

    $this
        ->actingAs($user)
        ->post(route('transfers.store', absolute: false), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'date' => '24-04-2026',
            'amount' => 0,
            'description' => 'Invalid',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::query()->whereNotNull('transfer_id')->count())->toBe(0);
    Event::assertDispatched(TransferFailedValidation::class);
});

test('cannot transfer between different currencies', function () {
    Event::fake([TransferFailedValidation::class]);

    $user = User::factory()->create();

    DB::table('currencies')->updateOrInsert(
        ['code' => 'USD'],
        [
            'name' => 'US Dollar',
            'symbol' => '$',
            'precision' => 2,
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );

    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $usdId = (int) Currency::query()->where('code', 'USD')->value('id');

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From PLN',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $usdId,
        'name' => 'To USD',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($user)
        ->post(route('transfers.store', absolute: false), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'date' => '24-04-2026',
            'amount' => 10,
            'description' => 'Invalid',
        ])
        ->assertSessionHasErrors('to_account_id');

    expect(Transaction::query()->whereNotNull('transfer_id')->count())->toBe(0);
    Event::assertDispatched(TransferFailedValidation::class);
});

test('dedupe does not block creating the same transfer twice', function () {
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

    $payload = [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'date' => '24-04-2026',
        'amount' => 10,
        'description' => 'Same transfer',
    ];

    $this->actingAs($user)->post(route('transfers.store', absolute: false), $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('transfers.store', absolute: false), $payload)->assertSessionHasNoErrors();

    expect(Transaction::query()->where('user_id', $user->id)->whereNotNull('transfer_id')->count())->toBe(4);
    expect(Transaction::query()->where('user_id', $user->id)->whereNotNull('transfer_id')->distinct('transfer_id')->count('transfer_id'))->toBe(2);
});

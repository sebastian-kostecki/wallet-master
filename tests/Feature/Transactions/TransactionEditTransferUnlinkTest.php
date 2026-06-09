<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
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

test('edit page exposes transfer_id for linked leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => '-50.00',
    ]);

    $withdrawal = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $from->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '-50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer out',
        'subject' => null,
        'normalized_description' => 'transfer out',
        'dedupe_hash' => md5('edit-out', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.edit', $withdrawal, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Edit', false)
            ->where('transaction.transfer_id', $transferId)
        );
});

test('unlink from edit redirects back and clears transfer_id', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => '-50.00',
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => '50.00',
    ]);

    $withdrawal = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $from->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '-50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer out',
        'subject' => null,
        'normalized_description' => 'transfer out',
        'dedupe_hash' => md5('edit-unlink-out', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $to->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer in',
        'subject' => null,
        'normalized_description' => 'transfer in',
        'dedupe_hash' => md5('edit-unlink-in', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    $this->actingAs($user)
        ->from(route('transactions.edit', $withdrawal))
        ->post(route('transfers.unlink', $transferId))
        ->assertRedirect(route('transactions.edit', $withdrawal))
        ->assertSessionHas('toast.message_key', 'transfers.toast.unlinked');

    $withdrawal->refresh();

    expect($withdrawal->transfer_id)->toBeNull();
    expect($withdrawal->type)->toBe(TransactionType::Expense);
});

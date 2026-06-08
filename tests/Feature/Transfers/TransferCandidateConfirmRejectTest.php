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

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createManualTransferPair(User $user, Account $from, Account $to): array
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $withdrawal = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $from->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '-80.00',
        'type' => TransactionType::Expense,
        'description' => 'Out',
        'subject' => null,
        'normalized_description' => 'out',
        'dedupe_hash' => md5('manual-out', true),
        'transfer_match_status' => TransferMatchStatus::Manual,
    ]);

    $deposit = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $to->id,
        'currency_id' => $plnId,
        'date' => '2026-04-02',
        'booked_at' => '2026-04-02',
        'amount' => '80.00',
        'type' => TransactionType::Income,
        'description' => 'In',
        'subject' => null,
        'normalized_description' => 'in',
        'dedupe_hash' => md5('manual-in', true),
        'transfer_match_status' => TransferMatchStatus::Manual,
    ]);

    $withdrawal->update(['transfer_candidate_for_id' => $deposit->id]);
    $deposit->update(['transfer_candidate_for_id' => $withdrawal->id]);

    return [$withdrawal->fresh(), $deposit->fresh()];
}

test('user can confirm a pending transfer candidate pair', function () {
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

    [$withdrawal, $deposit] = createManualTransferPair($user, $from, $to);

    $this->actingAs($user)
        ->from(route('transactions.index', absolute: false))
        ->post(route('transfers.candidates.confirm', $withdrawal))
        ->assertRedirect();

    $withdrawal->refresh();
    $deposit->refresh();

    expect($withdrawal->transfer_id)->not->toBeNull();
    expect($withdrawal->transfer_id)->toBe($deposit->transfer_id);
    expect($withdrawal->type)->toBe(TransactionType::Transfer);
    expect($withdrawal->transfer_match_status)->toBe(TransferMatchStatus::Manual);
    expect($withdrawal->transfer_candidate_for_id)->toBeNull();
    expect($deposit->transfer_candidate_for_id)->toBeNull();
});

test('user can reject a pending transfer candidate pair', function () {
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

    [$withdrawal, $deposit] = createManualTransferPair($user, $from, $to);

    $this->actingAs($user)
        ->post(route('transfers.candidates.reject', $withdrawal))
        ->assertRedirect();

    $withdrawal->refresh();
    $deposit->refresh();

    expect($withdrawal->transfer_match_status)->toBe(TransferMatchStatus::Rejected);
    expect($deposit->transfer_match_status)->toBe(TransferMatchStatus::Rejected);
    expect($withdrawal->transfer_candidate_for_id)->toBeNull();
    expect($deposit->transfer_candidate_for_id)->toBeNull();
});

test('other user cannot confirm transfer candidate', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $from = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $to = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    [$withdrawal] = createManualTransferPair($owner, $from, $to);

    $this->actingAs($other)
        ->post(route('transfers.candidates.confirm', $withdrawal))
        ->assertForbidden();
});

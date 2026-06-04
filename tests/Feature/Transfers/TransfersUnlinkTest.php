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

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can unlink a linked transfer and restore income expense types', function () {
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
        'current_balance' => -50,
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 50,
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
        'dedupe_hash' => md5('unlink-out', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    $deposit = Transaction::query()->create([
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
        'dedupe_hash' => md5('unlink-in', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    $this->actingAs($user)
        ->post(route('transfers.unlink', $transferId))
        ->assertRedirect();

    $withdrawal->refresh();
    $deposit->refresh();

    expect($withdrawal->transfer_id)->toBeNull();
    expect($deposit->transfer_id)->toBeNull();
    expect($withdrawal->type)->toBe(TransactionType::Expense);
    expect($deposit->type)->toBe(TransactionType::Income);
    expect($withdrawal->transfer_match_status)->toBe(TransferMatchStatus::Rejected);
    expect($deposit->transfer_match_status)->toBe(TransferMatchStatus::Rejected);
    expect($withdrawal->category_id)->not->toBeNull();
    expect($deposit->category_id)->not->toBeNull();
    expect($withdrawal->goal_id)->toBeNull();
    expect($deposit->goal_id)->toBeNull();
});

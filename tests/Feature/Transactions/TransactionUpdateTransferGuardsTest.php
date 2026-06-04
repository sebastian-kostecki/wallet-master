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

/**
 * @return array{0: Transaction, 1: Transaction, 2: Account, 3: Account}
 */
function createLinkedTransferPair(User $user, int $plnId, string $transferId): array
{
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
        'dedupe_hash' => md5('guard-out', true),
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
        'dedupe_hash' => md5('guard-in', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    return [$withdrawal, $deposit, $from, $to];
}

test('cannot change amount on a linked transfer leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    [$withdrawal, , $from] = createLinkedTransferPair($user, $plnId, $transferId);

    $this->actingAs($user)
        ->put("/transactions/{$withdrawal->id}", [
            'account_id' => $from->id,
            'date' => '01-04-2026',
            'amount' => -99,
            'description' => 'Transfer out',
            'subject' => null,
        ])
        ->assertSessionHasErrors(['amount']);

    $withdrawal->refresh();
    expect($withdrawal->amount)->toBe('-50.00');
    expect($from->refresh()->current_balance)->toBe('-50.00');
});

test('cannot change account on a linked transfer leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    [$withdrawal, , $from, $to] = createLinkedTransferPair($user, $plnId, $transferId);

    $this->actingAs($user)
        ->put("/transactions/{$withdrawal->id}", [
            'account_id' => $to->id,
            'date' => '01-04-2026',
            'amount' => -50,
            'description' => 'Transfer out',
            'subject' => null,
        ])
        ->assertSessionHasErrors(['account_id']);

    expect($withdrawal->refresh()->account_id)->toBe($from->id);
});

test('can update description on a linked transfer leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    [$withdrawal, , $from] = createLinkedTransferPair($user, $plnId, $transferId);

    $this->actingAs($user)
        ->put("/transactions/{$withdrawal->id}", [
            'account_id' => $from->id,
            'date' => '01-04-2026',
            'amount' => -50,
            'description' => 'Updated label',
            'subject' => 'Bank',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('transactions.edit', $withdrawal));

    $withdrawal->refresh();
    expect($withdrawal->description)->toBe('Updated label');
    expect($withdrawal->subject)->toBe('Bank');
    expect($withdrawal->type)->toBe(TransactionType::Transfer);
});

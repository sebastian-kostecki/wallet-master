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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('transactions index includes pending transfer candidates', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'B',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $withdrawal = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '-40.00',
        'type' => TransactionType::Expense,
        'description' => 'Candidate out',
        'subject' => null,
        'normalized_description' => 'candidate out',
        'dedupe_hash' => md5('index-out', true),
        'transfer_match_status' => TransferMatchStatus::Manual,
    ]);

    $deposit = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-02',
        'booked_at' => '2026-04-02',
        'amount' => '40.00',
        'type' => TransactionType::Income,
        'description' => 'Candidate in',
        'subject' => null,
        'normalized_description' => 'candidate in',
        'dedupe_hash' => md5('index-in', true),
        'transfer_match_status' => TransferMatchStatus::Manual,
        'transfer_candidate_for_id' => $withdrawal->id,
    ]);

    $withdrawal->update(['transfer_candidate_for_id' => $deposit->id]);

    $this->actingAs($user)
        ->get(route('transactions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->has('pending_transfer_candidates', 1)
            ->where('pending_transfer_candidates.0.anchor_transaction_id', $withdrawal->id)
            ->where('pending_transfer_candidates.0.amount', '40.00')
        );

    $this->actingAs($user)
        ->get(route('transactions.index', ['account_id' => $accountB->id], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pending_transfer_candidates', 1)
        );

    $this->actingAs($user)
        ->post(route('transfers.candidates.confirm', $withdrawal))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('transactions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pending_transfer_candidates', 0)
        );
});

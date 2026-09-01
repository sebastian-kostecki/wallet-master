<?php

declare(strict_types=1);

use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createDepositCandidateBankAccount(User $user): Account
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    return Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '10000.00',
        'current_balance' => '10000.00',
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createDepositCandidateTransaction(User $user, Account $account, array $overrides = []): Transaction
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $amount = $overrides['amount'] ?? '-3000.00';
    $date = $overrides['date'] ?? '2026-08-01';
    $description = $overrides['description'] ?? 'Deposit candidate';

    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => $date,
        'booked_at' => $date,
        'amount' => $amount,
        'type' => bccomp((string) $amount, '0', 2) === -1 ? TransactionType::Expense : TransactionType::Income,
        'description' => $description,
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription($description),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash($date, (string) $amount, uniqid('deposit-candidate-', true)),
        'category_id' => null,
        'deposit_match_status' => DepositMatchStatus::Manual,
    ], $overrides));
}

test('user can confirm an open deposit candidate', function () {
    $user = User::factory()->create();
    $account = createDepositCandidateBankAccount($user);

    $candidate = createDepositCandidateTransaction($user, $account, [
        'amount' => '-3000.00',
        'deposit_candidate_kind' => 'open',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.index', absolute: false))
        ->post(route('deposits.candidates.confirm', $candidate))
        ->assertRedirect();

    $candidate->refresh();

    expect($candidate->deposit_match_status)->toBe(DepositMatchStatus::Manual);
    expect($candidate->deposit_candidate_kind)->toBeNull();
    expect($candidate->category_id)->toBeNull();

    $movement = $candidate->depositMovement;
    expect($movement)->not->toBeNull();
    expect($movement->kind)->toBe(DepositMovementKind::Open);

    $deposit = $movement->deposit;
    expect($deposit->status)->toBe(DepositStatus::Active);
    expect($deposit->principal)->toBe('3000.00');
    expect($deposit->source_account_id)->toBe($account->id);
});

test('user can confirm a close deposit candidate with chosen deposit_id', function () {
    $user = User::factory()->create();
    $account = createDepositCandidateBankAccount($user);

    $firstDeposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '5000.00',
        'opened_at' => '01-07-2026',
        'name' => 'First 5k',
    ]);

    app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '5000.00',
        'opened_at' => '01-08-2026',
        'name' => 'Second 5k',
    ]);

    $candidate = createDepositCandidateTransaction($user, $account, [
        'amount' => '5000.00',
        'date' => '2026-08-15',
        'deposit_candidate_kind' => 'close_principal',
        'description' => 'Deposit close candidate',
    ]);

    $this->actingAs($user)
        ->post(route('deposits.candidates.confirm', $candidate), [
            'deposit_id' => $firstDeposit->id,
        ])
        ->assertRedirect();

    $candidate->refresh();
    $firstDeposit->refresh();

    expect($candidate->deposit_match_status)->toBe(DepositMatchStatus::Manual);
    expect($candidate->deposit_candidate_kind)->toBeNull();
    expect($candidate->category_id)->toBeNull();

    $movement = $candidate->depositMovement;
    expect($movement)->not->toBeNull();
    expect($movement->kind)->toBe(DepositMovementKind::ClosePrincipal);
    expect($movement->deposit_id)->toBe($firstDeposit->id);

    expect($firstDeposit->status)->toBe(DepositStatus::Closed);
    expect($firstDeposit->closed_at?->toDateString())->toBe('2026-08-15');
});

test('user can reject a pending deposit candidate', function () {
    $user = User::factory()->create();
    $account = createDepositCandidateBankAccount($user);

    $candidate = createDepositCandidateTransaction($user, $account, [
        'amount' => '-3000.00',
        'deposit_candidate_kind' => 'open',
    ]);

    $this->actingAs($user)
        ->post(route('deposits.candidates.reject', $candidate))
        ->assertRedirect();

    $candidate->refresh();

    expect($candidate->deposit_match_status)->toBe(DepositMatchStatus::Rejected);
    expect($candidate->deposit_candidate_kind)->toBeNull();
    expect($candidate->category_id)->toBeNull();
    expect($candidate->depositMovement)->toBeNull();
});

test('other user cannot confirm deposit candidate', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = createDepositCandidateBankAccount($owner);

    $candidate = createDepositCandidateTransaction($owner, $account, [
        'amount' => '-3000.00',
        'deposit_candidate_kind' => 'open',
    ]);

    $this->actingAs($other)
        ->post(route('deposits.candidates.confirm', $candidate))
        ->assertForbidden();
});

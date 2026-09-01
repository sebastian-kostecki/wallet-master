<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
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

/**
 * @param  array<string, mixed>  $attributes
 */
function createDepositMatchTransaction(User $user, array $attributes = []): Transaction
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '0.00',
        'current_balance' => '0.00',
    ]);

    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-01',
        'booked_at' => '2026-08-01',
        'amount' => '-1000.00',
        'type' => TransactionType::Expense,
        'description' => 'Deposit row',
        'normalized_description' => TransactionDedupe::normalizeDescription('Deposit row'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-01', '-1000.00', 'deposit-row-'.uniqid('', true)),
        'category_id' => null,
        'deposit_match_status' => DepositMatchStatus::None,
    ], $attributes));
}

test('transaction persists deposit match status and candidate kind', function () {
    $user = User::factory()->create();

    $transaction = createDepositMatchTransaction($user, [
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => 'open',
        'category_id' => null,
    ]);

    expect($transaction->fresh()->deposit_match_status)->toBe(DepositMatchStatus::Manual);
    expect($transaction->fresh()->deposit_candidate_kind)->toBe('open');
});

test('pending deposit candidate scope returns manual rows without movement', function () {
    $user = User::factory()->create();

    $candidate = createDepositMatchTransaction($user, [
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => 'close_principal',
        'category_id' => null,
    ]);

    createDepositMatchTransaction($user, [
        'deposit_match_status' => DepositMatchStatus::None,
    ]);

    $ids = Transaction::query()
        ->whereBelongsTo($user)
        ->pendingDepositCandidate()
        ->pluck('id');

    expect($ids)->toContain($candidate->id);
    expect($ids)->toHaveCount(1);
});

<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('deposit persists active status and an open movement', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '0.00',
        'current_balance' => '0.00',
    ]);
    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-01',
        'booked_at' => '2026-08-01',
        'amount' => '-1000.00',
        'type' => 'expense',
        'description' => 'Open',
        'normalized_description' => TransactionDedupe::normalizeDescription('Open'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-01', '-1000.00', 'open'),
        'category_id' => null,
    ]);

    $deposit = Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => '3M mBank',
        'principal' => '1000.00',
        'opened_at' => '2026-08-01',
        'matures_at' => '2026-11-01',
        'status' => DepositStatus::Active,
        'closed_at' => null,
    ]);

    DepositMovement::query()->create([
        'deposit_id' => $deposit->id,
        'transaction_id' => $transaction->id,
        'kind' => DepositMovementKind::Open,
    ]);

    $deposit->refresh();
    expect($deposit->status)->toBe(DepositStatus::Active);
    expect($deposit->principal)->toBe('1000.00');
    expect($deposit->movements)->toHaveCount(1);
    expect($transaction->fresh()->depositMovement?->kind)->toBe(DepositMovementKind::Open);
});

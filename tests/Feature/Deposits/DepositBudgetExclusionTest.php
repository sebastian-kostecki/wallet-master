<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('transaction summary excludes deposit capital and includes interest', function () {
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

    $incomeCategory = Category::factory()->income()->for($user)->create();

    Category::factory()->expense()->for($user)->create();

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $incomeCategory->id,
        'date' => '2026-08-05',
        'booked_at' => '2026-08-05',
        'amount' => '100.00',
        'type' => 'income',
        'description' => 'Salary',
        'normalized_description' => TransactionDedupe::normalizeDescription('Salary'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-05', '100.00', 'salary'),
    ]);

    $openTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-10',
        'booked_at' => '2026-08-10',
        'amount' => '-1000.00',
        'type' => 'expense',
        'description' => 'Open deposit',
        'normalized_description' => TransactionDedupe::normalizeDescription('Open deposit'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-10', '-1000.00', 'open-deposit'),
        'category_id' => null,
    ]);

    $deposit = Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => '3M mBank',
        'principal' => '1000.00',
        'opened_at' => '2026-08-10',
        'matures_at' => '2026-11-10',
        'status' => DepositStatus::Active,
        'closed_at' => null,
    ]);

    DepositMovement::query()->create([
        'deposit_id' => $deposit->id,
        'transaction_id' => $openTransaction->id,
        'kind' => DepositMovementKind::Open,
    ]);

    $interestTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $incomeCategory->id,
        'date' => '2026-08-15',
        'booked_at' => '2026-08-15',
        'amount' => '50.00',
        'type' => 'income',
        'description' => 'Deposit interest',
        'normalized_description' => TransactionDedupe::normalizeDescription('Deposit interest'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-15', '50.00', 'deposit-interest'),
    ]);

    DepositMovement::query()->create([
        'deposit_id' => $deposit->id,
        'transaction_id' => $interestTransaction->id,
        'kind' => DepositMovementKind::Interest,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', ['from' => '01-08-2026', 'to' => '31-08-2026'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_income', 150)
            ->where('summary.total_expense', 0)
        );
});

test('transaction summary excludes pending deposit import candidates', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => '0.00',
        'current_balance' => '0.00',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-10',
        'booked_at' => '2026-08-10',
        'amount' => '-5000.00',
        'type' => 'expense',
        'description' => 'OTWARCIE LOKATY candidate',
        'normalized_description' => TransactionDedupe::normalizeDescription('OTWARCIE LOKATY candidate'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-10', '-5000.00', 'deposit-open-candidate'),
        'category_id' => null,
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', ['from' => '01-08-2026', 'to' => '31-08-2026'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_income', 0)
            ->where('summary.total_expense', 0)
        );
});

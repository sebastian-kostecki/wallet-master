<?php

declare(strict_types=1);

use App\Actions\Deposits\CreateDeposit;
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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('transactions index includes pending deposit candidates with matching deposits for close', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '10000.00',
        'current_balance' => '10000.00',
    ]);

    app(CreateDeposit::class)->handle($user, [
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

    $candidate = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-15',
        'booked_at' => '2026-08-15',
        'amount' => '5000.00',
        'type' => TransactionType::Income,
        'description' => 'Deposit close candidate',
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription('Deposit close candidate'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-15', '5000.00', 'deposit-close-candidate'),
        'category_id' => null,
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => 'close_principal',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-10',
        'booked_at' => '2026-08-10',
        'amount' => '-120.00',
        'type' => TransactionType::Expense,
        'description' => 'Regular expense',
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription('Regular expense'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-10', '-120.00', 'regular-expense'),
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->has('pending_deposit_candidates', 1)
            ->where('pending_deposit_candidates.0.transaction_id', $candidate->id)
            ->where('pending_deposit_candidates.0.kind', 'close_principal')
            ->has('pending_deposit_candidates.0.matching_deposits', 2)
        );

    $this->actingAs($user)
        ->get(route('transactions.index', ['deposit_candidates' => 1], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $candidate->id)
            ->where('transactions.data.0.deposit_candidate.kind', 'close_principal')
        );
});

test('confirmed deposit candidates are removed from pending banner list', function () {
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

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-08-10',
        'booked_at' => '2026-08-10',
        'amount' => '-3000.00',
        'type' => TransactionType::Expense,
        'description' => 'Confirmed deposit open',
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription('Confirmed deposit open'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash('2026-08-10', '-3000.00', 'confirmed-deposit-open'),
        'category_id' => null,
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => null,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pending_deposit_candidates', 0)
        );
});

<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Goals\GoalBalance;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('cumulative balance sums savings transfer legs across all months', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Checking',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 1000,
        'current_balance' => 1000,
    ]);
    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    $transferId = (string) Str::uuid();

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'goal_id' => $goal->id,
        'date' => '2026-01-15',
        'booked_at' => '2026-01-15',
        'amount' => '-300.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('save-out-1', true),
        'transfer_id' => $transferId,
    ]);
    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'goal_id' => $goal->id,
        'date' => '2026-01-15',
        'booked_at' => '2026-01-15',
        'amount' => '300.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('save-in-1', true),
        'transfer_id' => $transferId,
    ]);

    $transferId2 = (string) Str::uuid();
    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'goal_id' => $goal->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '-100.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('release-out', true),
        'transfer_id' => $transferId2,
    ]);
    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'goal_id' => $goal->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '100.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('release-in', true),
        'transfer_id' => $transferId2,
    ]);

    $result = GoalBalance::cumulative($user, $goal);

    expect($result)->toBe([
        'saved_total' => '300.00',
        'released_total' => '100.00',
        'balance' => '200.00',
    ]);
});

test('cumulative balance ignores savings legs in a different currency than the goal', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    Currency::query()->create([
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'precision' => 2,
    ]);
    $eurId = (int) Currency::query()->where('code', 'EUR')->value('id');

    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);
    $savingsPln = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings PLN',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);
    $savingsEur = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $eurId,
        'name' => 'Savings EUR',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savingsPln->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'goal_id' => $goal->id,
        'date' => '2026-01-15',
        'booked_at' => '2026-01-15',
        'amount' => '100.00',
        'type' => TransactionType::Transfer,
        'description' => 'Save PLN',
        'normalized_description' => 'save pln',
        'dedupe_hash' => md5('save-pln', true),
        'transfer_id' => (string) Str::uuid(),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savingsEur->id,
        'currency_id' => $eurId,
        'category_id' => null,
        'goal_id' => $goal->id,
        'date' => '2026-02-15',
        'booked_at' => '2026-02-15',
        'amount' => '999.00',
        'type' => TransactionType::Transfer,
        'description' => 'Save EUR',
        'normalized_description' => 'save eur',
        'dedupe_hash' => md5('save-eur', true),
        'transfer_id' => (string) Str::uuid(),
    ]);

    $result = GoalBalance::cumulative($user, $goal);

    expect($result['balance'])->toBe('100.00');
});

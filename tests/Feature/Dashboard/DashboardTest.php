<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function dashboardPlnId(): int
{
    return (int) Currency::query()->where('code', 'PLN')->value('id');
}

function dashboardAccount(User $user, array $overrides = []): Account
{
    return Account::query()->create(array_merge([
        'user_id' => $user->id,
        'currency_id' => dashboardPlnId(),
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ], $overrides));
}

function dashboardTransaction(User $user, Account $account, array $overrides = []): Transaction
{
    $amount = $overrides['amount'] ?? '-20.00';
    $date = $overrides['date'] ?? '2026-08-10';
    $description = $overrides['description'] ?? 'Expense';
    $amountString = TransactionDedupe::amountToDecimalString((string) $amount);

    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => dashboardPlnId(),
        'date' => $date,
        'booked_at' => $overrides['booked_at'] ?? $date,
        'amount' => $amount,
        'type' => TransactionType::Expense,
        'description' => $description,
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription($description),
        'dedupe_hash' => TransactionDedupe::dedupeHash($date, $amountString, TransactionDedupe::normalizeDescription($description)),
        'category_id' => $overrides['category_id'] ?? null,
        'transfer_id' => null,
    ], $overrides));
}

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard', absolute: false))
        ->assertRedirect(route('login', absolute: false));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk();
});

test('dashboard includes only the authenticated user balances and omits accounts', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    dashboardAccount($userA, [
        'name' => 'Cash A',
        'current_balance' => 10,
        'opening_balance' => 10,
    ]);
    dashboardAccount($userA, [
        'name' => 'Savings A',
        'type' => AccountType::Savings,
        'current_balance' => 40,
        'opening_balance' => 40,
    ]);
    dashboardAccount($userB, [
        'name' => 'Cash B',
        'current_balance' => 99,
        'opening_balance' => 99,
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('accounts')
            ->where('period.year', 2026)
            ->where('period.month', 8)
            ->where('balances.total_pln', '50.00')
            ->where('balances.savings_pln', '40.00')
            ->where('balances.deposits_pln', '0.00')
            ->where('currency.code', 'PLN')
        );
});

test('dashboard deposits_pln sums active PLN deposits only', function () {
    $user = User::factory()->create();
    $account = dashboardAccount($user, [
        'current_balance' => 50,
        'opening_balance' => 50,
    ]);

    Deposit::factory()->create([
        'user_id' => $user->id,
        'source_account_id' => $account->id,
        'currency_id' => dashboardPlnId(),
        'principal' => '1000.00',
        'status' => DepositStatus::Active,
    ]);

    Deposit::factory()->create([
        'user_id' => $user->id,
        'source_account_id' => $account->id,
        'currency_id' => dashboardPlnId(),
        'principal' => '500.00',
        'status' => DepositStatus::Closed,
        'closed_at' => '2026-08-01',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('balances.total_pln', '50.00')
            ->where('balances.deposits_pln', '1000.00')
        );
});

test('dashboard cashflow is the current month P&L excluding transfers and other users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    ensureUserCategories($userA);

    $accountA = dashboardAccount($userA);
    $accountB = dashboardAccount($userB);
    $food = Category::query()
        ->where('user_id', $userA->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();
    $salary = Category::query()
        ->where('user_id', $userA->id)
        ->where('type', 'income')
        ->orderBy('id')
        ->firstOrFail();

    dashboardTransaction($userA, $accountA, [
        'amount' => '300.00',
        'type' => TransactionType::Income,
        'description' => 'Salary',
        'category_id' => $salary->id,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '300.00', 'salary'),
        'normalized_description' => 'salary',
    ]);
    dashboardTransaction($userA, $accountA, [
        'amount' => '-80.00',
        'description' => 'Groceries',
        'category_id' => $food->id,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '-80.00', 'groceries'),
        'normalized_description' => 'groceries',
    ]);
    dashboardTransaction($userA, $accountA, [
        'amount' => '-50.00',
        'date' => '2026-07-10',
        'booked_at' => '2026-07-10',
        'description' => 'July',
        'category_id' => $food->id,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-07-10', '-50.00', 'july'),
        'normalized_description' => 'july',
    ]);
    dashboardTransaction($userB, $accountB, [
        'amount' => '-999.00',
        'description' => 'Other',
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '-999.00', 'other'),
        'normalized_description' => 'other',
    ]);

    $transferId = 'dash-transfer-uuid';
    dashboardTransaction($userA, $accountA, [
        'amount' => '-25.00',
        'type' => TransactionType::Transfer,
        'description' => 'Move',
        'transfer_id' => $transferId,
        'category_id' => null,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '-25.00', 'move-out'),
        'normalized_description' => 'move-out',
    ]);
    dashboardTransaction($userA, $accountA, [
        'amount' => '25.00',
        'type' => TransactionType::Transfer,
        'description' => 'Move',
        'transfer_id' => $transferId,
        'category_id' => null,
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-10', '25.00', 'move-in'),
        'normalized_description' => 'move-in',
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cashflow.income', '300.00')
            ->where('cashflow.expense', '80.00')
            ->where('budget_summary.execution.income', '300.00')
            ->where('budget_summary.execution.expense', '80.00')
        );
});

test('dashboard recent transactions are the latest eight for the current user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $accountA = dashboardAccount($userA);
    $accountB = dashboardAccount($userB);

    dashboardTransaction($userB, $accountB, [
        'description' => 'Other user',
        'dedupe_hash' => TransactionDedupe::dedupeHash('2026-08-20', '-20.00', 'other user'),
        'normalized_description' => 'other user',
    ]);

    foreach (range(1, 9) as $day) {
        $date = sprintf('2026-08-%02d', $day);
        dashboardTransaction($userA, $accountA, [
            'date' => $date,
            'booked_at' => $date,
            'description' => "Day {$day}",
            'amount' => '-1.00',
            'dedupe_hash' => TransactionDedupe::dedupeHash($date, '-1.00', "day {$day}"),
            'normalized_description' => "day {$day}",
        ]);
    }

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recent_transactions', 8)
            ->where('recent_transactions.0.description', 'Day 9')
            ->where('recent_transactions.7.description', 'Day 2')
            ->where('recent_transactions', fn ($rows) => collect($rows)->contains('description', 'Other user') === false)
        );
});

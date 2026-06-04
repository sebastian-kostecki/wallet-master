<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Currency;
use App\Models\Goal;
use App\Models\GoalMonthlyEstimate;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('monthly budget uses annual divided by twelve when no monthly override', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'amount' => 12000,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('budget/Monthly', false)
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['monthly_plan'] === '1000.00')
    );
});

test('monthly budget uses monthly override when set', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'amount' => 12000,
    ]);

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 1500,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['monthly_plan'] === '1500.00')
    );
});

test('monthly budget goal row tracks save and release on savings account', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $goal = Goal::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    GoalMonthlyEstimate::query()->create([
        'goal_id' => $goal->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 500,
    ]);

    $checking = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Oszczędności',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $savingsCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Oszczędności')
        ->firstOrFail();

    $saveTransferId = 'monthly-save-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => $savingsCategory->id,
        'goal_id' => $goal->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '-200.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('xfer-out', true),
        'transfer_id' => $saveTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => $savingsCategory->id,
        'goal_id' => $goal->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '200.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('xfer-in', true),
        'transfer_id' => $saveTransferId,
    ]);

    $releaseTransferId = 'monthly-release-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => $savingsCategory->id,
        'goal_id' => $goal->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '-150.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('xfer-out-release', true),
        'transfer_id' => $releaseTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => $savingsCategory->id,
        'goal_id' => $goal->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '150.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('xfer-in-release', true),
        'transfer_id' => $releaseTransferId,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['monthly_plan'] === '500.00')
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['saved'] === '200.00')
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['released'] === '150.00')
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['balance'] === '50.00')
        ->where('goal_rows', fn ($rows) => collect($rows)->firstWhere('goal_id', $goal->id)['linked_expenses'] === '0.00')
    );
});

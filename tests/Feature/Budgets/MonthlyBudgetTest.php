<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\PocketPlanningMode;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Currency;
use App\Models\Pocket;
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

test('monthly budget pocket row tracks save and release on savings account', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'name' => 'Wakacje',
        'target_amount' => '6000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '500.00',
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

    $saveTransferId = 'monthly-save-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
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
        'category_id' => null,
        'pocket_id' => $pocket->id,
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
        'category_id' => null,
        'pocket_id' => $pocket->id,
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
        'category_id' => null,
        'pocket_id' => $pocket->id,
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
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['monthly_plan'] === '500.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['saved'] === '200.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['released'] === '150.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['balance'] === '50.00')
        ->where('pocket_rows', fn ($rows) => collect($rows)->firstWhere('pocket_id', $pocket->id)['currency']['code'] === 'PLN')
    );
});

test('monthly budget summary includes pocket saved as expense and released as income', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->ordered()
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $expenseCategory->id,
        'year' => 2026,
        'amount' => 3600,
    ]);

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'target_amount' => '6000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '500.00',
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

    $saveTransferId = 'summary-save-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '-200.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('summary-xfer-out', true),
        'transfer_id' => $saveTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '200.00',
        'type' => TransactionType::Transfer,
        'description' => 'To savings',
        'normalized_description' => 'to savings',
        'dedupe_hash' => md5('summary-xfer-in', true),
        'transfer_id' => $saveTransferId,
    ]);

    $releaseTransferId = 'summary-release-transfer-uuid';

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '-150.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('summary-xfer-out-release', true),
        'transfer_id' => $releaseTransferId,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '150.00',
        'type' => TransactionType::Transfer,
        'description' => 'From savings',
        'normalized_description' => 'from savings',
        'dedupe_hash' => md5('summary-xfer-in-release', true),
        'transfer_id' => $releaseTransferId,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.plan.expense', '800.00')
        ->where('summary.execution.expense', '200.00')
        ->where('summary.execution.income', '150.00')
        ->where('summary.execution.balance', '-50.00')
    );
});

test('monthly budget summary plan expense includes pocket monthly plan', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    Pocket::factory()->create([
        'user_id' => $user->id,
        'target_amount' => '6000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '500.00',
    ]);

    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->ordered()
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $expenseCategory->id,
        'year' => 2026,
        'amount' => 3600,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.plan.expense', '800.00')
    );
});

test('monthly budget exposes summary currency and progress without difference', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $incomeCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'income')
        ->ordered()
        ->firstOrFail();

    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->ordered()
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $incomeCategory->id,
        'year' => 2026,
        'amount' => 6000,
    ]);

    CategoryAnnualEstimate::query()->create([
        'category_id' => $expenseCategory->id,
        'year' => 2026,
        'amount' => 3600,
    ]);

    $response = $this->actingAs($user)->get('/budget/monthly?year=2026&month=3');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('summary')
        ->where('summary.plan.income', '500.00')
        ->where('summary.plan.expense', '300.00')
        ->where('currency.code', 'PLN')
        ->where('rows', fn ($rows) => ! array_key_exists('difference', collect($rows)->firstWhere('category_id', $expenseCategory->id)))
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $expenseCategory->id)['progress_percent'] === 0)
    );
});

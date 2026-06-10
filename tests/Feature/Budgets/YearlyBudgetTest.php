<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('yearly budget excludes internal transfers from category actuals', function () {
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

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $food->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '-100.00',
        'type' => TransactionType::Expense,
        'description' => 'Groceries',
        'normalized_description' => 'groceries',
        'dedupe_hash' => md5('food-1', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $food->id,
        'date' => '2026-03-20',
        'booked_at' => '2026-03-20',
        'amount' => '-50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer',
        'normalized_description' => 'transfer',
        'dedupe_hash' => md5('transfer-1', true),
        'transfer_id' => 'test-transfer-uuid',
    ]);

    $response = $this->actingAs($user)->get(route('budget.yearly', ['year' => 2026], absolute: false));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('budget/Yearly', false)
        ->has('rows')
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['actual_expense'] === '100.00')
    );
});

test('yearly budget exposes forecast and summary for current year', function () {
    Carbon::setTestNow('2026-05-15');

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

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 1500,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $food->id,
        'date' => '2026-03-15',
        'booked_at' => '2026-03-15',
        'amount' => '-100.00',
        'type' => TransactionType::Expense,
        'description' => 'Groceries',
        'normalized_description' => 'groceries-forecast',
        'dedupe_hash' => md5('food-forecast', true),
    ]);

    $response = $this->actingAs($user)->get(route('budget.yearly', ['year' => 2026], absolute: false));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('summary')
        ->has('currency')
        ->where('currency.code', 'PLN')
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['forecast'] === '6600.00')
        ->where('summary.forecast.expense', '6600.00')
        ->where('rows', fn ($rows) => ! array_key_exists('difference', collect($rows)->firstWhere('category_id', $food->id)))
    );

    Carbon::setTestNow();
});

test('yearly budget exposes monthly_template for uniform eligible overrides', function () {
    Carbon::setTestNow('2026-06-15');

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

    foreach (range(6, 12) as $month) {
        CategoryMonthlyEstimate::query()->create([
            'category_id' => $food->id,
            'year' => 2026,
            'month' => $month,
            'amount' => 400,
        ]);
    }

    $response = $this->actingAs($user)->get(route('budget.yearly', ['year' => 2026], absolute: false));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('category_id', $food->id)['monthly_template'] === '400.00')
    );

    Carbon::setTestNow();
});

<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create a category', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $response = $this->actingAs($user)->post('/categories', [
        'name' => 'Hobby',
        'type' => CategoryType::Expense->value,
    ]);

    $response->assertSessionHasNoErrors();
    expect(Category::where('user_id', $user->id)->where('name', 'Hobby')->exists())->toBeTrue();
});

test('cannot delete category with transactions', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $categoryId = defaultCategoryId($user);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $categoryId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => '-10.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('coffee-del', true),
    ]);

    $this->actingAs($user)->delete("/categories/{$categoryId}")->assertForbidden();
});

test('categories index only lists own categories', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    ensureUserCategories($userA);
    ensureUserCategories($userB);

    $this->actingAs($userA)->post('/categories', [
        'name' => 'Only A',
        'type' => CategoryType::Expense->value,
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($userB)->get('/categories');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('categories')
        ->where('categories', fn ($categories) => collect($categories)->pluck('name')->doesntContain('Only A'))
    );
});

test('cannot delete system savings category', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $savings = Category::where('user_id', $user->id)->where('name', 'Oszczędności')->firstOrFail();

    $this->actingAs($user)->delete("/categories/{$savings->id}")->assertForbidden();
});

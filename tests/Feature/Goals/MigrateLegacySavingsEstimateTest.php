<?php

use App\Actions\Goals\MigrateLegacySavingsEstimate;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Goal;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user with Oszczędności estimate gets default goal with same annual amount', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $savingsCategory = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Oszczędności',
        'type' => CategoryType::Expense,
        'is_system' => true,
        'sort_order' => 25,
    ]);

    CategoryAnnualEstimate::query()->create([
        'category_id' => $savingsCategory->id,
        'year' => 2026,
        'amount' => 5000,
    ]);

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $savingsCategory->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 500,
    ]);

    app(MigrateLegacySavingsEstimate::class)->handle();

    $goal = Goal::query()->where('user_id', $user->id)->first();
    expect($goal)->not->toBeNull();
    expect($goal->name)->toBe('Oszczędności ogólne');
    expect((string) $goal->target_amount)->toBe('5000.00');
    expect($goal->planning_mode?->value)->toBe('monthly');
    expect((string) $goal->monthly_contribution)->toBe('416.66');
    expect($goal->target_date)->toBeNull();
    expect($goal->is_archived)->toBeFalse();
});

test('legacy savings migration skips users who already have goals', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    Goal::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $savingsCategory = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Oszczędności',
        'type' => CategoryType::Expense,
        'is_system' => true,
        'sort_order' => 25,
    ]);

    CategoryAnnualEstimate::query()->create([
        'category_id' => $savingsCategory->id,
        'year' => 2026,
        'amount' => 5000,
    ]);

    app(MigrateLegacySavingsEstimate::class)->handle();

    expect(Goal::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(Schema::hasTable('goal_annual_estimates'))->toBeFalse();
    expect(Schema::hasTable('goal_monthly_estimates'))->toBeFalse();
});

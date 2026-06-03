<?php

use App\Actions\Goals\MigrateLegacySavingsEstimate;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Goal;
use App\Models\GoalAnnualEstimate;
use App\Models\GoalMonthlyEstimate;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user with Oszczędności estimate gets default goal with same annual amount', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $savingsCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('is_system', true)
        ->where('name', 'Oszczędności')
        ->firstOrFail();

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

    expect(GoalAnnualEstimate::query()
        ->where('goal_id', $goal->id)
        ->where('year', 2026)
        ->value('amount'))
        ->toBe('5000.00');

    expect(GoalMonthlyEstimate::query()
        ->where('goal_id', $goal->id)
        ->where('year', 2026)
        ->where('month', 3)
        ->value('amount'))
        ->toBe('500.00');
});

test('legacy savings migration skips users who already have goals', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    Goal::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $savingsCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('is_system', true)
        ->where('name', 'Oszczędności')
        ->firstOrFail();

    CategoryAnnualEstimate::query()->create([
        'category_id' => $savingsCategory->id,
        'year' => 2026,
        'amount' => 5000,
    ]);

    app(MigrateLegacySavingsEstimate::class)->handle();

    expect(Goal::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(GoalAnnualEstimate::query()->count())->toBe(0);
});

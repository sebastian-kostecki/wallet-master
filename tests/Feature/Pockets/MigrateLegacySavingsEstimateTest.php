<?php

use App\Actions\Pockets\MigrateLegacySavingsEstimate;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Pocket;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user with Oszczędności estimate gets default pocket with same annual amount', function () {
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

    $pocket = Pocket::query()->where('user_id', $user->id)->first();
    expect($pocket)->not->toBeNull();
    expect($pocket->name)->toBe('Oszczędności ogólne');
    expect((string) $pocket->target_amount)->toBe('5000.00');
    expect($pocket->planning_mode?->value)->toBe('monthly');
    expect((string) $pocket->monthly_contribution)->toBe('416.66');
    expect($pocket->target_date)->toBeNull();
    expect($pocket->is_archived)->toBeFalse();
});

test('legacy savings migration skips users who already have pockets', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

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

    expect(Pocket::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(Schema::hasTable('g'.'oal_annual_estimates'))->toBeFalse();
    expect(Schema::hasTable('g'.'oal_monthly_estimates'))->toBeFalse();
});

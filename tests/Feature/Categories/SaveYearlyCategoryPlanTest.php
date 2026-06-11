<?php

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\User;
use Illuminate\Support\Carbon;

test('yearly plan save updates annual and bulk monthly for eligible months without override', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 1500,
    ]);

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 9,
        'amount' => 2000,
    ]);

    $response = $this->actingAs($user)->patch(route('categories.estimates.yearly-plan', $food, absolute: false), [
        'year' => 2026,
        'annual_amount' => 5000,
        'monthly_amount' => 400,
    ]);

    $response->assertSessionHasNoErrors();

    $annual = CategoryAnnualEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->first();

    expect($annual)->not->toBeNull();
    expect((string) $annual->amount)->toBe('5000.00');

    expect((string) CategoryMonthlyEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->where('month', 3)
        ->value('amount'))->toBe('1500.00');

    foreach ([6, 7, 8, 10, 11, 12] as $month) {
        expect((string) CategoryMonthlyEstimate::query()
            ->where('category_id', $food->id)
            ->where('year', 2026)
            ->where('month', $month)
            ->value('amount'))->toBe('400.00');
    }

    expect((string) CategoryMonthlyEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->where('month', 9)
        ->value('amount'))->toBe('2000.00');

    Carbon::setTestNow();
});

test('empty monthly amount does not change existing monthly overrides', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    CategoryMonthlyEstimate::query()->create([
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 8,
        'amount' => 750,
    ]);

    $this->actingAs($user)->patch(route('categories.estimates.yearly-plan', $food, absolute: false), [
        'year' => 2026,
        'annual_amount' => 6000,
        'monthly_amount' => null,
    ])->assertSessionHasNoErrors();

    expect((string) CategoryAnnualEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->value('amount'))->toBe('6000.00');

    expect((string) CategoryMonthlyEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->where('month', 8)
        ->value('amount'))->toBe('750.00');

    Carbon::setTestNow();
});

test('yearly plan save for past year does not write monthly estimates', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Artykuły spożywcze')
        ->firstOrFail();

    $this->actingAs($user)->patch(route('categories.estimates.yearly-plan', $food, absolute: false), [
        'year' => 2025,
        'annual_amount' => 4000,
        'monthly_amount' => 300,
    ])->assertSessionHasNoErrors();

    expect(CategoryMonthlyEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2025)
        ->count())->toBe(0);

    Carbon::setTestNow();
});

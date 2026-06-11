<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\CategoryPlanAmount;

test('monthly display returns override amount when set', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $monthly = new CategoryMonthlyEstimate(['amount' => '1500.00']);

    expect(CategoryPlanAmount::monthly($category, 2026, 3, null, $monthly))->toBe('1500.00');
});

test('monthly display returns zero when no override even with annual', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    expect(CategoryPlanAmount::monthly($category, 2026, 3, $annual, null))->toBe('0.00');
});

test('monthlyForForecast returns annual divided by twelve when no override', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    expect(CategoryPlanAmount::monthlyForForecast($category, 2026, 3, $annual, null))->toBe('1000.00');
});

test('monthlyForForecast prefers override over annual', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);
    $monthly = new CategoryMonthlyEstimate(['amount' => '1500.00']);

    expect(CategoryPlanAmount::monthlyForForecast($category, 2026, 3, $annual, $monthly))->toBe('1500.00');
});

test('monthlyForForecast returns null when no annual and no override', function () {
    $category = new Category(['type' => CategoryType::Expense]);

    expect(CategoryPlanAmount::monthlyForForecast($category, 2026, 3, null, null))->toBeNull();
});

<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\BudgetForecast;
use Illuminate\Support\Collection;

test('referenceMonth returns current month for current year', function () {
    expect(BudgetForecast::referenceMonth(2026, 2026, 5))->toBe(5);
});

test('referenceMonth returns 12 for past year and 0 for future year', function () {
    expect(BudgetForecast::referenceMonth(2025, 2026, 5))->toBe(12);
    expect(BudgetForecast::referenceMonth(2027, 2026, 5))->toBe(0);
});

test('closedMonthForForecast returns previous month for current year', function () {
    expect(BudgetForecast::closedMonthForForecast(2026, 2026, 5))->toBe(4);
});

test('closedMonthForForecast returns 12 for past year and 0 for future year', function () {
    expect(BudgetForecast::closedMonthForForecast(2025, 2026, 5))->toBe(12);
    expect(BudgetForecast::closedMonthForForecast(2027, 2026, 5))->toBe(0);
});

test('forecast adds remaining annual plan after elapsed monthly plans', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);

    $elapsed = BudgetForecast::elapsedPlansSum(
        $category,
        2026,
        4,
        $annual,
        new Collection,
    );

    expect($elapsed)->toBe('4000.00');
    expect(BudgetForecast::forecast('4200.00', '12000.00', $elapsed))->toBe('12200.00');
});

test('forecast uses monthly overrides in elapsed sum', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => '12000.00']);
    $overrides = new Collection([
        3 => new CategoryMonthlyEstimate(['amount' => '1500.00']),
    ]);

    $elapsed = BudgetForecast::elapsedPlansSum($category, 2026, 3, $annual, $overrides);

    expect($elapsed)->toBe('3500.00');
});

test('forecast returns actual only when annual plan is null', function () {
    expect(BudgetForecast::forecast('4200.00', null, '0.00'))->toBe('4200.00');
});

test('forecast clamps negative remainder to zero', function () {
    expect(BudgetForecast::forecast('9000.00', '12000.00', '13000.00'))->toBe('9000.00');
});

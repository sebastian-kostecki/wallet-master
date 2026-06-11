<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\YearlyMonthlyTemplate;
use Illuminate\Support\Collection;

test('eligibleMonths returns empty for past year', function () {
    expect(YearlyMonthlyTemplate::eligibleMonths(2025, 2026, 6))->toBe([]);
});

test('eligibleMonths returns current through december for current year', function () {
    expect(YearlyMonthlyTemplate::eligibleMonths(2026, 2026, 6))->toBe([6, 7, 8, 9, 10, 11, 12]);
});

test('eligibleMonths returns all months for future year', function () {
    expect(YearlyMonthlyTemplate::eligibleMonths(2027, 2026, 6))->toBe(range(1, 12));
});

test('template returns null when eligible months mix explicit and derived plans', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => 12000]);
    $monthly = new Collection([
        6 => new CategoryMonthlyEstimate(['month' => 6, 'amount' => 400]),
    ]);

    expect(YearlyMonthlyTemplate::template(2026, $monthly, $annual, $category, 2026, 6))->toBeNull();
});

test('template returns shared amount when all eligible months have same explicit override', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => 12000]);
    $monthly = new Collection(
        collect(range(6, 12))
            ->mapWithKeys(fn (int $month) => [
                $month => new CategoryMonthlyEstimate(['month' => $month, 'amount' => 400]),
            ])
            ->all(),
    );

    expect(YearlyMonthlyTemplate::template(2026, $monthly, $annual, $category, 2026, 6))->toBe('400.00');
});

test('template returns null when eligible explicit amounts differ', function () {
    $category = new Category(['type' => CategoryType::Expense]);
    $annual = new CategoryAnnualEstimate(['amount' => 12000]);
    $monthly = new Collection([
        6 => new CategoryMonthlyEstimate(['month' => 6, 'amount' => 400]),
        7 => new CategoryMonthlyEstimate(['month' => 7, 'amount' => 500]),
    ]);

    expect(YearlyMonthlyTemplate::template(2026, $monthly, $annual, $category, 2026, 6))->toBeNull();
});

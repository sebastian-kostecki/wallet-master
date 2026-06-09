<?php

use App\Support\Budgets\BudgetProgress;

test('percent returns rounded execution ratio when plan is positive', function () {
    expect(BudgetProgress::percent('1000.00', '420.00'))->toBe(42);
    expect(BudgetProgress::percent('1000.00', '1000.00'))->toBe(100);
    expect(BudgetProgress::percent('1000.00', '1050.00'))->toBe(105);
});

test('percent returns null when plan is null or zero', function () {
    expect(BudgetProgress::percent(null, '100.00'))->toBeNull();
    expect(BudgetProgress::percent('0.00', '100.00'))->toBeNull();
    expect(BudgetProgress::percent('0', '0.00'))->toBeNull();
});

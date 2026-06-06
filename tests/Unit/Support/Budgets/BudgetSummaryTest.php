<?php

use App\Support\Budgets\BudgetSummary;

test('fromRows aggregates monthly plan and execution by type', function () {
    $rows = [
        [
            'type' => 'income',
            'monthly_plan' => '5000.00',
            'actual' => '4800.00',
        ],
        [
            'type' => 'expense',
            'monthly_plan' => '3000.00',
            'actual' => '3200.00',
        ],
    ];

    $summary = BudgetSummary::fromRows($rows, planKey: 'monthly_plan');

    expect($summary['plan']['income'])->toBe('5000.00')
        ->and($summary['plan']['expense'])->toBe('3000.00')
        ->and($summary['plan']['balance'])->toBe('2000.00')
        ->and($summary['execution']['income'])->toBe('4800.00')
        ->and($summary['execution']['expense'])->toBe('3200.00')
        ->and($summary['execution']['balance'])->toBe('1600.00')
        ->and($summary['progress']['income_percent'])->toBe(96)
        ->and($summary['progress']['expense_percent'])->toBe(107);
});

test('fromRows includes forecast totals when forecast key provided', function () {
    $rows = [
        ['type' => 'income', 'annual_plan' => '60000.00', 'actual' => '20000.00', 'forecast' => '58000.00'],
        ['type' => 'expense', 'annual_plan' => '36000.00', 'actual' => '15000.00', 'forecast' => '34000.00'],
    ];

    $summary = BudgetSummary::fromRows($rows, planKey: 'annual_plan', forecastKey: 'forecast');

    expect($summary['forecast']['income'])->toBe('58000.00')
        ->and($summary['forecast']['expense'])->toBe('34000.00')
        ->and($summary['forecast']['balance'])->toBe('24000.00');
});

test('withPockets adds plan to expense and saved and released to execution', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'income', 'monthly_plan' => '5000.00', 'actual' => '4800.00'],
        ['type' => 'expense', 'monthly_plan' => '3000.00', 'actual' => '3200.00'],
    ], planKey: 'monthly_plan');

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => '200.00',
            'saved' => '200.00',
            'released' => '150.00',
            'currency' => ['code' => 'PLN'],
        ],
    ], 'PLN');

    expect($merged['plan']['income'])->toBe('5000.00')
        ->and($merged['plan']['expense'])->toBe('3200.00')
        ->and($merged['plan']['balance'])->toBe('1800.00')
        ->and($merged['execution']['income'])->toBe('4950.00')
        ->and($merged['execution']['expense'])->toBe('3400.00')
        ->and($merged['execution']['balance'])->toBe('1550.00');
});

test('withPockets skips null monthly_plan but still merges execution', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'expense', 'monthly_plan' => '1000.00', 'actual' => '800.00'],
    ], planKey: 'monthly_plan');

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => null,
            'saved' => '100.00',
            'released' => '50.00',
            'currency' => ['code' => 'PLN'],
        ],
    ], 'PLN');

    expect($merged['plan']['expense'])->toBe('1000.00')
        ->and($merged['execution']['expense'])->toBe('900.00')
        ->and($merged['execution']['income'])->toBe('50.00');
});

test('withPockets skips pockets with non-matching currency', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'expense', 'monthly_plan' => '1000.00', 'actual' => '0.00'],
    ], planKey: 'monthly_plan');

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => '500.00',
            'saved' => '200.00',
            'released' => '100.00',
            'currency' => ['code' => 'EUR'],
        ],
    ], 'PLN');

    expect($merged['plan']['expense'])->toBe('1000.00')
        ->and($merged['execution']['expense'])->toBe('0.00')
        ->and($merged['execution']['income'])->toBe('0.00');
});

test('withPockets recalculates expense_percent only and preserves income_percent', function () {
    $summary = BudgetSummary::fromRows([
        ['type' => 'income', 'monthly_plan' => '1000.00', 'actual' => '500.00'],
        ['type' => 'expense', 'monthly_plan' => '1000.00', 'actual' => '800.00'],
    ], planKey: 'monthly_plan');

    expect($summary['progress']['income_percent'])->toBe(50)
        ->and($summary['progress']['expense_percent'])->toBe(80);

    $merged = BudgetSummary::withPockets($summary, [
        [
            'monthly_plan' => '200.00',
            'saved' => '400.00',
            'released' => '100.00',
            'currency' => ['code' => 'PLN'],
        ],
    ], 'PLN');

    expect($merged['progress']['income_percent'])->toBe(50)
        ->and($merged['progress']['expense_percent'])->toBe(100);
});

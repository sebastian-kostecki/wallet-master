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

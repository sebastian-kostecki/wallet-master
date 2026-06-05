<?php

declare(strict_types=1);

namespace App\Support\Budgets;

final class BudgetSummary
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     plan: array{income: string, expense: string, balance: string},
     *     execution: array{income: string, expense: string, balance: string},
     *     progress: array{income_percent: int|null, expense_percent: int|null},
     *     forecast?: array{income: string, expense: string, balance: string}
     * }
     */
    public static function fromRows(
        array $rows,
        string $planKey,
        ?string $forecastKey = null,
    ): array {
        $planIncome = '0.00';
        $planExpense = '0.00';
        $actualIncome = '0.00';
        $actualExpense = '0.00';
        $forecastIncome = '0.00';
        $forecastExpense = '0.00';

        foreach ($rows as $row) {
            $plan = $row[$planKey] ?? null;
            $actual = (string) ($row['actual'] ?? '0.00');

            if ($row['type'] === 'income') {
                if ($plan !== null) {
                    $planIncome = bcadd($planIncome, (string) $plan, 2);
                }
                $actualIncome = bcadd($actualIncome, $actual, 2);

                if ($forecastKey !== null && isset($row[$forecastKey])) {
                    $forecastIncome = bcadd($forecastIncome, (string) $row[$forecastKey], 2);
                }
            }

            if ($row['type'] === 'expense') {
                if ($plan !== null) {
                    $planExpense = bcadd($planExpense, (string) $plan, 2);
                }
                $actualExpense = bcadd($actualExpense, $actual, 2);

                if ($forecastKey !== null && isset($row[$forecastKey])) {
                    $forecastExpense = bcadd($forecastExpense, (string) $row[$forecastKey], 2);
                }
            }
        }

        $summary = [
            'plan' => [
                'income' => $planIncome,
                'expense' => $planExpense,
                'balance' => bcsub($planIncome, $planExpense, 2),
            ],
            'execution' => [
                'income' => $actualIncome,
                'expense' => $actualExpense,
                'balance' => bcsub($actualIncome, $actualExpense, 2),
            ],
            'progress' => [
                'income_percent' => BudgetProgress::percent($planIncome, $actualIncome),
                'expense_percent' => BudgetProgress::percent($planExpense, $actualExpense),
            ],
        ];

        if ($forecastKey !== null) {
            $summary['forecast'] = [
                'income' => $forecastIncome,
                'expense' => $forecastExpense,
                'balance' => bcsub($forecastIncome, $forecastExpense, 2),
            ];
        }

        return $summary;
    }
}

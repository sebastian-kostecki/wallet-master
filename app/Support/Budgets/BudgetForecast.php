<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use Illuminate\Support\Collection;

final class BudgetForecast
{
    public static function closedMonthForForecast(int $viewYear, ?int $nowYear = null, ?int $nowMonth = null): int
    {
        $nowYear ??= (int) now()->format('Y');
        $nowMonth ??= (int) now()->format('n');

        if ($viewYear < $nowYear) {
            return 12;
        }

        if ($viewYear > $nowYear) {
            return 0;
        }

        return max(0, $nowMonth - 1);
    }

    public static function referenceMonth(int $viewYear, ?int $nowYear = null, ?int $nowMonth = null): int
    {
        $nowYear ??= (int) now()->format('Y');
        $nowMonth ??= (int) now()->format('n');

        if ($viewYear < $nowYear) {
            return 12;
        }

        if ($viewYear > $nowYear) {
            return 0;
        }

        return $nowMonth;
    }

    /**
     * @param  Collection<int, CategoryMonthlyEstimate>  $monthlyEstimatesByMonth  keyed by month 1-12
     */
    public static function elapsedPlansSum(
        Category $category,
        int $year,
        int $throughMonth,
        ?CategoryAnnualEstimate $annual,
        Collection $monthlyEstimatesByMonth,
    ): string {
        $sum = '0.00';

        for ($month = 1; $month <= $throughMonth; $month++) {
            $monthly = $monthlyEstimatesByMonth->get($month);
            $plan = CategoryPlanAmount::monthlyForForecast($category, $year, $month, $annual, $monthly);

            if ($plan !== null) {
                $sum = bcadd($sum, $plan, 2);
            }
        }

        return $sum;
    }

    public static function forecast(string $actualYtd, ?string $annualPlan, string $elapsedPlansSum): string
    {
        if ($annualPlan === null) {
            return $actualYtd;
        }

        $remainder = bcsub($annualPlan, $elapsedPlansSum, 2);

        if (bccomp($remainder, '0', 2) < 0) {
            $remainder = '0.00';
        }

        return bcadd($actualYtd, $remainder, 2);
    }
}

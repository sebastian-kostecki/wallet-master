<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use Illuminate\Support\Collection;

final class YearlyMonthlyTemplate
{
    /**
     * @return list<int>
     */
    public static function eligibleMonths(int $year, ?int $nowYear = null, ?int $nowMonth = null): array
    {
        $nowYear ??= (int) now()->format('Y');
        $nowMonth ??= (int) now()->format('n');

        if ($year < $nowYear) {
            return [];
        }

        if ($year > $nowYear) {
            return range(1, 12);
        }

        return range($nowMonth, 12);
    }

    /**
     * @param  Collection<int, CategoryMonthlyEstimate>  $monthlyEstimatesByMonth
     */
    public static function template(
        int $year,
        Collection $monthlyEstimatesByMonth,
        ?CategoryAnnualEstimate $annual,
        Category $category,
        ?int $nowYear = null,
        ?int $nowMonth = null,
    ): ?string {
        $eligible = self::eligibleMonths($year, $nowYear, $nowMonth);
        $amounts = [];

        foreach ($eligible as $month) {
            $monthly = $monthlyEstimatesByMonth->get($month);

            if ($monthly?->amount === null) {
                return null;
            }

            $amounts[] = (string) $monthly->amount;
        }

        if ($amounts === []) {
            return null;
        }

        $unique = array_unique($amounts);

        return count($unique) === 1 ? $unique[0] : null;
    }

    public static function hasExistingOverride(?CategoryMonthlyEstimate $monthly): bool
    {
        return $monthly?->amount !== null;
    }
}

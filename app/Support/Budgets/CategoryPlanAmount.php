<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;

final class CategoryPlanAmount
{
    public static function monthly(
        Category $category,
        int $year,
        int $month,
        ?CategoryAnnualEstimate $annual,
        ?CategoryMonthlyEstimate $monthly,
    ): ?string {
        if ($monthly?->amount !== null) {
            return (string) $monthly->amount;
        }

        if ($annual?->amount !== null) {
            return bcdiv((string) $annual->amount, '12', 2);
        }

        return null;
    }

    public static function annual(?CategoryAnnualEstimate $annual): ?string
    {
        if ($annual?->amount === null) {
            return null;
        }

        return (string) $annual->amount;
    }
}

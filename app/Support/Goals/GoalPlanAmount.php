<?php

declare(strict_types=1);

namespace App\Support\Goals;

use App\Models\Goal;
use App\Models\GoalAnnualEstimate;
use App\Models\GoalMonthlyEstimate;

final class GoalPlanAmount
{
    public static function monthly(
        Goal $goal,
        int $year,
        int $month,
        ?GoalAnnualEstimate $annual,
        ?GoalMonthlyEstimate $monthly,
    ): ?string {
        if ($monthly?->amount !== null) {
            return (string) $monthly->amount;
        }

        if ($annual?->amount !== null) {
            return bcdiv((string) $annual->amount, '12', 2);
        }

        return null;
    }

    public static function annual(?GoalAnnualEstimate $annual): ?string
    {
        if ($annual?->amount === null) {
            return null;
        }

        return (string) $annual->amount;
    }
}

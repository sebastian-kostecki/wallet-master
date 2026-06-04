<?php

declare(strict_types=1);

namespace App\Support\Goals;

use App\Enums\GoalPlanningMode;
use App\Models\Goal;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class GoalPlanningProjection
{
    public static function recommendedMonthly(Goal $goal, string $balance): ?string
    {
        if ($goal->planning_mode !== GoalPlanningMode::ByDate || $goal->target_amount === null || $goal->target_date === null) {
            return null;
        }

        $remaining = bcsub((string) $goal->target_amount, $balance, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return '0.00';
        }

        $now = Carbon::now()->startOfMonth();
        $targetMonth = Carbon::parse($goal->target_date)->startOfMonth();

        if ($targetMonth->lt($now)) {
            return $remaining;
        }

        $monthsLeft = max(1, $now->diffInMonths($targetMonth) + 1);

        return bcdiv($remaining, (string) $monthsLeft, 2);
    }

    /**
     * @param  array<string, string>  $monthlyNets  keys `YYYY-MM`, values net saved in month
     */
    public static function projectedCompletionDate(Goal $goal, string $balance, array $monthlyNets): ?CarbonInterface
    {
        if ($goal->planning_mode !== GoalPlanningMode::Monthly || $goal->target_amount === null || $goal->monthly_contribution === null) {
            return null;
        }

        $remaining = bcsub((string) $goal->target_amount, $balance, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return Carbon::today();
        }

        $positiveNets = array_values(array_filter($monthlyNets, fn (string $net): bool => bccomp($net, '0', 2) > 0));

        if ($positiveNets === []) {
            $effectiveRate = (string) $goal->monthly_contribution;
        } else {
            $sum = array_reduce($positiveNets, fn (string $carry, string $net): string => bcadd($carry, $net, 2), '0.00');
            $effectiveRate = bcdiv($sum, (string) count($positiveNets), 2);
        }

        if (bccomp($effectiveRate, '0', 2) <= 0) {
            return null;
        }

        $monthsNeeded = (int) ceil((float) bcdiv($remaining, $effectiveRate, 4));
        $monthsNeeded = max(1, $monthsNeeded);

        return Carbon::now()->startOfMonth()->addMonths($monthsNeeded)->endOfMonth();
    }

    public static function monthlyPlanForBudget(Goal $goal, string $balance): ?string
    {
        return match ($goal->planning_mode) {
            GoalPlanningMode::Monthly => $goal->monthly_contribution !== null ? (string) $goal->monthly_contribution : null,
            GoalPlanningMode::ByDate => self::recommendedMonthly($goal, $balance),
            default => null,
        };
    }

    public static function isOverdue(Goal $goal, string $balance): bool
    {
        if ($goal->planning_mode !== GoalPlanningMode::ByDate || $goal->target_date === null || $goal->target_amount === null) {
            return false;
        }

        return Carbon::parse($goal->target_date)->isPast() && bccomp($balance, (string) $goal->target_amount, 2) < 0;
    }

    /**
     * Build monthly net map for projection from transaction query results.
     *
     * @param  iterable<object{ym: string, net: string}>  $rows
     * @return array<string, string>
     */
    public static function monthlyNetMapFromRows(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row->ym] = TransactionDedupe::amountToDecimalString((string) $row->net);
        }

        return $map;
    }
}

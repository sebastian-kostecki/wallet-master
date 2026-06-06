<?php

declare(strict_types=1);

namespace App\Support\Pockets;

use App\Enums\PocketPlanningMode;
use App\Models\Pocket;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PocketPlanningProjection
{
    public static function recommendedMonthly(Pocket $pocket, string $balance): ?string
    {
        if ($pocket->planning_mode !== PocketPlanningMode::ByDate || $pocket->target_amount === null || $pocket->target_date === null) {
            return null;
        }

        $remaining = bcsub((string) $pocket->target_amount, $balance, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return '0.00';
        }

        $now = Carbon::now()->startOfMonth();
        $targetMonth = Carbon::parse($pocket->target_date)->startOfMonth();

        if ($targetMonth->lt($now)) {
            return $remaining;
        }

        $monthsLeft = max(1, $now->diffInMonths($targetMonth) + 1);

        return bcdiv($remaining, (string) $monthsLeft, 2);
    }

    /**
     * @param  array<string, string>  $monthlyNets  keys `YYYY-MM`, values net saved in month
     */
    public static function projectedCompletionDate(Pocket $pocket, string $balance, array $monthlyNets): ?CarbonInterface
    {
        if ($pocket->planning_mode !== PocketPlanningMode::Monthly || $pocket->target_amount === null || $pocket->monthly_contribution === null) {
            return null;
        }

        $remaining = bcsub((string) $pocket->target_amount, $balance, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return Carbon::today();
        }

        $positiveNets = array_values(array_filter($monthlyNets, fn (string $net): bool => bccomp($net, '0', 2) > 0));

        if ($positiveNets === []) {
            $effectiveRate = (string) $pocket->monthly_contribution;
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

    public static function monthlyPlanForBudget(Pocket $pocket, string $balance): ?string
    {
        return match ($pocket->planning_mode) {
            PocketPlanningMode::Monthly => $pocket->monthly_contribution !== null ? (string) $pocket->monthly_contribution : null,
            PocketPlanningMode::ByDate => self::recommendedMonthly($pocket, $balance),
            default => null,
        };
    }

    public static function isOverdue(Pocket $pocket, string $balance): bool
    {
        if ($pocket->planning_mode !== PocketPlanningMode::ByDate || $pocket->target_date === null || $pocket->target_amount === null) {
            return false;
        }

        return Carbon::parse($pocket->target_date)->isPast() && bccomp($balance, (string) $pocket->target_amount, 2) < 0;
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

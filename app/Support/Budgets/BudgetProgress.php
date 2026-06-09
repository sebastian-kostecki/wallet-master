<?php

declare(strict_types=1);

namespace App\Support\Budgets;

final class BudgetProgress
{
    public static function percent(?string $plan, string $actual): ?int
    {
        if ($plan === null || bccomp($plan, '0', 2) <= 0) {
            return null;
        }

        $ratio = bcmul(bcdiv($actual, $plan, 4), '100', 2);

        return (int) round((float) $ratio);
    }
}

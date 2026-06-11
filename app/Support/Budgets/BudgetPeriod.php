<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use Carbon\CarbonImmutable;

final readonly class BudgetPeriod
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function forMonth(int $year, int $month): self
    {
        $start = CarbonImmutable::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->endOfDay();

        return new self($start, $end);
    }

    public static function forYear(int $year): self
    {
        $start = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::createFromDate($year, 12, 31)->endOfDay();

        return new self($start, $end);
    }

    public static function throughMonth(int $year, int $month): self
    {
        $start = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        return new self($start, $end);
    }
}

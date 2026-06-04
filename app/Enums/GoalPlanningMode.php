<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalPlanningMode: string
{
    case Monthly = 'monthly';
    case ByDate = 'by_date';
}

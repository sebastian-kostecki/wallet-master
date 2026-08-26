<?php

declare(strict_types=1);

namespace App\Support\Pockets;

use App\Enums\PocketMovement;
use App\Models\Pocket;
use App\Models\User;
use App\Support\Budgets\BudgetPeriod;

final class PocketTransactionMetrics
{
    /**
     * @return array{saved: string, released: string, balance: string}
     */
    public static function forMonth(User $user, Pocket $pocket, BudgetPeriod $period): array
    {
        $saved = PocketMovementTotal::sum($user, $pocket, PocketMovement::Deposit, $period);
        $released = PocketMovementTotal::sum($user, $pocket, PocketMovement::Withdrawal, $period);
        $balance = bcsub($saved, $released, 2);

        return [
            'saved' => $saved,
            'released' => $released,
            'balance' => $balance,
        ];
    }
}

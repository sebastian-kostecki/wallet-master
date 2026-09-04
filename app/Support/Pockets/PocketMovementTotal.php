<?php

declare(strict_types=1);

namespace App\Support\Pockets;

use App\Enums\PocketMovement;
use App\Models\Pocket;
use App\Models\User;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;

final class PocketMovementTotal
{
    /**
     * @return numeric-string
     */
    public static function sum(User $user, Pocket $pocket, PocketMovement $movement, ?BudgetPeriod $period = null): string
    {
        $query = BudgetTransactionQuery::forUser($user)
            ->where('pocket_id', $pocket->id)
            ->where('pocket_movement', $movement)
            ->whereNotNull('transfer_id')
            ->where('currency_id', $pocket->currency_id);

        if ($period !== null) {
            BudgetTransactionQuery::inPeriod($query, $period);
        }

        $amounts = $query
            ->groupBy('transfer_id')
            ->selectRaw('MAX(ABS(amount)) as amt')
            ->pluck('amt');

        $total = '0.00';
        foreach ($amounts as $amt) {
            $total = bcadd($total, TransactionDedupe::amountToDecimalString((string) $amt), 2);
        }

        return $total;
    }
}

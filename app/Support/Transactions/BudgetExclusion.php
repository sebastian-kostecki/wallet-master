<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use App\Enums\DepositMovementKind;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

final class BudgetExclusion
{
    /**
     * @param  Builder<Transaction>  $query
     */
    public static function apply(Builder $query): void
    {
        $query
            ->whereNull('transfer_id')
            ->whereDoesntHave('depositMovement', function (Builder $movement): void {
                $movement->whereIn('kind', [
                    DepositMovementKind::Open->value,
                    DepositMovementKind::ClosePrincipal->value,
                ]);
            });
    }
}

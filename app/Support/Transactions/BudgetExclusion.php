<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use App\Enums\DepositMatchStatus;
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
            })
            ->where(function (Builder $pendingCandidate): void {
                $pendingCandidate
                    ->where('deposit_match_status', '!=', DepositMatchStatus::Manual)
                    ->orWhereNull('deposit_candidate_kind');
            })
            ->where(function (Builder $rejectedCandidate): void {
                $rejectedCandidate
                    ->where('deposit_match_status', '!=', DepositMatchStatus::Rejected)
                    ->orWhereNotNull('category_id');
            });
    }
}

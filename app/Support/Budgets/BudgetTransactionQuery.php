<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BudgetTransactionQuery
{
    /**
     * @return Builder<Transaction>
     */
    public static function forUser(User $user): Builder
    {
        return Transaction::query()->where('user_id', $user->id);
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public static function inPeriod(Builder $query, BudgetPeriod $period): void
    {
        $from = $period->from->toDateString();
        $to = $period->to->toDateString();

        $query->whereRaw(
            'DATE('.self::displayDateSqlExpression($query).') >= ?',
            [$from],
        );
        $query->whereRaw(
            'DATE('.self::displayDateSqlExpression($query).') <= ?',
            [$to],
        );
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public static function excludeTransfers(Builder $query): void
    {
        $query->whereNull('transfer_id');
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private static function displayDateSqlExpression(Builder $query): string
    {
        $grammar = $query->getGrammar();
        $bookedAt = $grammar->wrap($query->qualifyColumn('booked_at'));
        $date = $grammar->wrap($query->qualifyColumn('date'));

        return "COALESCE({$bookedAt}, {$date})";
    }
}

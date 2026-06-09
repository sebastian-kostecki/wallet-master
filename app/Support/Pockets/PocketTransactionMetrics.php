<?php

declare(strict_types=1);

namespace App\Support\Pockets;

use App\Enums\AccountType;
use App\Models\Pocket;
use App\Models\User;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;

final class PocketTransactionMetrics
{
    /**
     * @return array{saved: string, released: string, balance: string, linked_expenses: string}
     */
    public static function forMonth(User $user, Pocket $pocket, BudgetPeriod $period): array
    {
        $savedQuery = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($savedQuery, $period);
        $savedSum = $savedQuery
            ->where('pocket_id', $pocket->id)
            ->whereNotNull('transfer_id')
            ->where('amount', '>', 0)
            ->whereHas('account', fn ($q) => $q
                ->where('type', AccountType::Savings)
                ->where('currency_id', $pocket->currency_id))
            ->sum('amount');

        $saved = TransactionDedupe::amountToDecimalString((string) $savedSum);

        $releasedQuery = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($releasedQuery, $period);
        $releasedSum = $releasedQuery
            ->where('pocket_id', $pocket->id)
            ->whereNotNull('transfer_id')
            ->where('amount', '<', 0)
            ->whereHas('account', fn ($q) => $q
                ->where('type', AccountType::Savings)
                ->where('currency_id', $pocket->currency_id))
            ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
            ->value('total');

        $released = TransactionDedupe::amountToDecimalString((string) $releasedSum);

        $balance = bcsub($saved, $released, 2);

        $linkedQuery = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($linkedQuery, $period);
        BudgetTransactionQuery::excludeTransfers($linkedQuery);
        $linkedSum = $linkedQuery
            ->where('pocket_id', $pocket->id)
            ->where('amount', '<', 0)
            ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
            ->value('total');

        $linkedExpenses = TransactionDedupe::amountToDecimalString((string) $linkedSum);

        return [
            'saved' => $saved,
            'released' => $released,
            'balance' => $balance,
            'linked_expenses' => $linkedExpenses,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Pockets;

use App\Enums\AccountType;
use App\Models\Pocket;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;

final class PocketBalance
{
    /**
     * @return array{saved_total: string, released_total: string, balance: string}
     */
    public static function cumulative(User $user, Pocket $pocket): array
    {
        $savedQuery = BudgetTransactionQuery::forUser($user);
        $savedSum = $savedQuery
            ->where('pocket_id', $pocket->id)
            ->whereNotNull('transfer_id')
            ->where('amount', '>', 0)
            ->whereHas('account', fn ($q) => $q
                ->where('type', AccountType::Savings)
                ->where('currency_id', $pocket->currency_id))
            ->sum('amount');

        $savedTotal = TransactionDedupe::amountToDecimalString((string) $savedSum);

        $releasedQuery = BudgetTransactionQuery::forUser($user);
        $releasedSum = $releasedQuery
            ->where('pocket_id', $pocket->id)
            ->whereNotNull('transfer_id')
            ->where('amount', '<', 0)
            ->whereHas('account', fn ($q) => $q
                ->where('type', AccountType::Savings)
                ->where('currency_id', $pocket->currency_id))
            ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
            ->value('total');

        $releasedTotal = TransactionDedupe::amountToDecimalString((string) $releasedSum);
        $transferNet = bcsub($savedTotal, $releasedTotal, 2);
        $balance = bcadd((string) $pocket->initial_balance, $transferNet, 2);

        return [
            'saved_total' => $savedTotal,
            'released_total' => $releasedTotal,
            'balance' => $balance,
        ];
    }

    public static function isCompleted(Pocket $pocket, string $balance): bool
    {
        if ($pocket->target_amount === null) {
            return false;
        }

        return bccomp($balance, (string) $pocket->target_amount, 2) >= 0;
    }

    public static function progressPercent(Pocket $pocket, string $balance): ?int
    {
        if ($pocket->target_amount === null || bccomp((string) $pocket->target_amount, '0', 2) === 0) {
            return null;
        }

        $ratio = bcdiv($balance, (string) $pocket->target_amount, 4);
        $percent = (int) bcmul($ratio, '100', 0);

        return min(100, max(0, $percent));
    }

    /**
     * @return array<string, string>
     */
    public static function monthlyNetMap(User $user, Pocket $pocket): array
    {
        $transactions = BudgetTransactionQuery::forUser($user)
            ->where('pocket_id', $pocket->id)
            ->whereNotNull('transfer_id')
            ->whereHas('account', fn ($q) => $q
                ->where('type', AccountType::Savings)
                ->where('currency_id', $pocket->currency_id))
            ->get(['booked_at', 'amount']);

        $map = [];

        foreach ($transactions as $transaction) {
            /** @var Transaction $transaction */
            $ym = $transaction->booked_at->format('Y-m');
            $map[$ym] = bcadd($map[$ym] ?? '0.00', TransactionDedupe::amountToDecimalString((string) $transaction->amount), 2);
        }

        return $map;
    }
}

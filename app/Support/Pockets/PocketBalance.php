<?php

declare(strict_types=1);

namespace App\Support\Pockets;

use App\Enums\PocketMovement;
use App\Models\Pocket;
use App\Models\User;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Support\Carbon;

final class PocketBalance
{
    /**
     * @return array{saved_total: string, released_total: string, balance: string}
     */
    public static function cumulative(User $user, Pocket $pocket): array
    {
        $savedTotal = PocketMovementTotal::sum($user, $pocket, PocketMovement::Deposit);
        $releasedTotal = PocketMovementTotal::sum($user, $pocket, PocketMovement::Withdrawal);
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
        $rows = BudgetTransactionQuery::forUser($user)
            ->where('pocket_id', $pocket->id)
            ->whereNotNull('transfer_id')
            ->where('currency_id', $pocket->currency_id)
            ->whereNotNull('pocket_movement')
            ->groupBy('transfer_id', 'pocket_movement')
            ->selectRaw('pocket_movement, MAX(booked_at) as booked_at, MAX(ABS(amount)) as amt')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $ym = Carbon::parse($row->booked_at)->format('Y-m');
            $amt = TransactionDedupe::amountToDecimalString((string) $row->amt);
            $movement = $row->pocket_movement instanceof PocketMovement
                ? $row->pocket_movement
                : PocketMovement::from((string) $row->pocket_movement);

            if ($movement === PocketMovement::Deposit) {
                $map[$ym] = bcadd($map[$ym] ?? '0.00', $amt, 2);
            } else {
                $map[$ym] = bcsub($map[$ym] ?? '0.00', $amt, 2);
            }
        }

        return $map;
    }
}

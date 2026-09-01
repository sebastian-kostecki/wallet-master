<?php

declare(strict_types=1);

namespace App\Support\Deposits;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class DepositCloser
{
    /**
     * @return Collection<int, Deposit>
     */
    public function matchingActiveDeposits(User $user, string $principal, int $currencyId): Collection
    {
        return Deposit::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->where('status', DepositStatus::Active)
            ->where('principal', $principal)
            ->get();
    }
}

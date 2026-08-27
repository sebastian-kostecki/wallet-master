<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Deposit;
use App\Models\User;

final class DepositPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deposit $deposit): bool
    {
        return $deposit->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Deposit $deposit): bool
    {
        return $deposit->user_id === $user->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

final class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        if ($transaction->user_id !== $user->id) {
            return false;
        }

        $transaction->loadMissing(['account' => fn ($q) => $q->withTrashed()]);

        return ! $transaction->account?->trashed();
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        if ($transaction->user_id !== $user->id) {
            return false;
        }

        $transaction->loadMissing(['account' => fn ($q) => $q->withTrashed()]);

        return ! $transaction->account?->trashed();
    }
}

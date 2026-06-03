<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TransferMatchStatus;
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

    public function confirmTransferCandidate(User $user, Transaction $transaction): bool
    {
        return $this->isPendingTransferCandidate($user, $transaction);
    }

    public function rejectTransferCandidate(User $user, Transaction $transaction): bool
    {
        return $this->isPendingTransferCandidate($user, $transaction);
    }

    private function isPendingTransferCandidate(User $user, Transaction $transaction): bool
    {
        if ($transaction->user_id !== $user->id) {
            return false;
        }

        if ($transaction->transfer_match_status !== TransferMatchStatus::Manual) {
            return false;
        }

        if ($transaction->transfer_candidate_for_id === null) {
            return false;
        }

        $transaction->loadMissing(['account' => fn ($q) => $q->withTrashed(), 'transferCandidate.account' => fn ($q) => $q->withTrashed()]);

        if ($transaction->account?->trashed() || $transaction->transferCandidate?->account?->trashed()) {
            return false;
        }

        return $transaction->transferCandidate?->transfer_candidate_for_id === $transaction->id;
    }
}

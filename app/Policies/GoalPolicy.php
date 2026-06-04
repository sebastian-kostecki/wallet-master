<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

final class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id;
    }

    public function delete(User $user, Goal $goal): bool
    {
        if ($goal->user_id !== $user->id) {
            return false;
        }

        return ! $goal->hasLinkedTransactions();
    }
}

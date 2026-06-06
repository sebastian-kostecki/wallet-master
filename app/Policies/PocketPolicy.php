<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pocket;
use App\Models\User;

final class PocketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pocket $pocket): bool
    {
        return $pocket->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Pocket $pocket): bool
    {
        return $pocket->user_id === $user->id;
    }

    public function delete(User $user, Pocket $pocket): bool
    {
        if ($pocket->user_id !== $user->id) {
            return false;
        }

        return ! $pocket->hasLinkedTransactions();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\User;

final class ReorderGoals
{
    /**
     * @param  list<int>  $ids
     */
    public function handle(User $user, array $ids): void
    {
        foreach ($ids as $index => $id) {
            Goal::query()
                ->where('user_id', $user->id)
                ->whereKey($id)
                ->update(['sort_order' => ($index + 1) * 10]);
        }
    }
}

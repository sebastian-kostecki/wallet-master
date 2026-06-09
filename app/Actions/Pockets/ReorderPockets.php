<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Models\Pocket;
use App\Models\User;

final class ReorderPockets
{
    /**
     * @param  list<int>  $ids
     */
    public function handle(User $user, array $ids): void
    {
        foreach ($ids as $index => $id) {
            Pocket::query()
                ->where('user_id', $user->id)
                ->whereKey($id)
                ->update(['sort_order' => ($index + 1) * 10]);
        }
    }
}

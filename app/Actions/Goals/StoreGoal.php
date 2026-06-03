<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\User;
use App\Telemetry\Event;

final class StoreGoal
{
    /**
     * @param  array{name: string}  $validated
     */
    public function handle(User $user, array $validated): Goal
    {
        $maxSort = (int) Goal::query()
            ->where('user_id', $user->id)
            ->max('sort_order');

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'sort_order' => $maxSort + 10,
        ]);

        Event::record('goal_created', ['goal_id' => $goal->id], $user->id);

        return $goal;
    }
}

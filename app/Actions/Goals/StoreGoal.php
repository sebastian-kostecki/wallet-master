<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\User;
use App\Telemetry\Event;

final class StoreGoal
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(User $user, array $validated): Goal
    {
        $maxSort = (int) Goal::query()
            ->where('user_id', $user->id)
            ->max('sort_order');

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'icon' => $validated['icon'],
            'color' => $validated['color'],
            'sort_order' => $maxSort + 10,
            'target_amount' => $validated['target_amount'] ?? null,
            'planning_mode' => $validated['planning_mode'] ?? null,
            'monthly_contribution' => $validated['monthly_contribution'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
            'is_archived' => false,
        ]);

        Event::record('goal_created', ['goal_id' => $goal->id], $user->id);

        return $goal;
    }
}

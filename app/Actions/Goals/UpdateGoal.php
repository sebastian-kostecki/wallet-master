<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Telemetry\Event;

final class UpdateGoal
{
    /**
     * @param  array{name?: string, sort_order?: int}  $validated
     */
    public function handle(Goal $goal, array $validated): Goal
    {
        if (isset($validated['name'])) {
            $goal->name = $validated['name'];
        }

        if (isset($validated['sort_order'])) {
            $goal->sort_order = $validated['sort_order'];
        }

        $goal->save();

        Event::record('goal_updated', ['goal_id' => $goal->id], $goal->user_id);

        return $goal;
    }
}

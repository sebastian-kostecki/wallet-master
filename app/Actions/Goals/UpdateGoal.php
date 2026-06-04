<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Telemetry\Event;

final class UpdateGoal
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(Goal $goal, array $validated): Goal
    {
        $wasArchived = $goal->is_archived;

        $fillable = [
            'name',
            'icon',
            'color',
            'target_amount',
            'planning_mode',
            'monthly_contribution',
            'target_date',
            'is_archived',
            'sort_order',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $validated)) {
                $goal->{$field} = $validated[$field];
            }
        }

        $goal->save();

        Event::record('goal_updated', ['goal_id' => $goal->id], $goal->user_id);

        if (array_key_exists('is_archived', $validated) && (bool) $validated['is_archived'] !== $wasArchived) {
            Event::record(
                (bool) $validated['is_archived'] ? 'goal_archived' : 'goal_unarchived',
                ['goal_id' => $goal->id],
                $goal->user_id,
            );
        }

        return $goal;
    }
}

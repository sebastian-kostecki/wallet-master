<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\GoalAnnualEstimate;
use App\Telemetry\Event;

final class SaveAnnualEstimate
{
    /**
     * @param  array{year: int, amount: ?numeric-string|float|int|null}  $validated
     */
    public function handle(Goal $goal, array $validated): GoalAnnualEstimate
    {
        $estimate = GoalAnnualEstimate::query()->updateOrCreate(
            [
                'goal_id' => $goal->id,
                'year' => $validated['year'],
            ],
            [
                'amount' => $validated['amount'] ?? null,
            ],
        );

        Event::record('goal_estimate_annual_saved', [
            'goal_id' => $goal->id,
            'year' => $validated['year'],
        ], $goal->user_id);

        return $estimate;
    }
}

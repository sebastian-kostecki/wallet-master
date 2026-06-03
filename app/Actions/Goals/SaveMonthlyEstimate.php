<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\GoalMonthlyEstimate;
use App\Telemetry\Event;

final class SaveMonthlyEstimate
{
    /**
     * @param  array{year: int, month: int, amount: ?numeric-string|float|int|null}  $validated
     */
    public function handle(Goal $goal, array $validated): GoalMonthlyEstimate
    {
        $estimate = GoalMonthlyEstimate::query()->updateOrCreate(
            [
                'goal_id' => $goal->id,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            [
                'amount' => $validated['amount'] ?? null,
            ],
        );

        Event::record('goal_estimate_monthly_saved', [
            'goal_id' => $goal->id,
            'year' => $validated['year'],
            'month' => $validated['month'],
        ], $goal->user_id);

        return $estimate;
    }
}

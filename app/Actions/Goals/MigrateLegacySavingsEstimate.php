<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Goal;
use App\Models\GoalAnnualEstimate;
use App\Models\GoalMonthlyEstimate;
use Illuminate\Support\Collection;

final class MigrateLegacySavingsEstimate
{
    private const DEFAULT_GOAL_NAME = 'Oszczędności ogólne';

    public function handle(): void
    {
        $estimates = CategoryAnnualEstimate::query()
            ->with('category')
            ->whereHas('category', fn ($query) => $query
                ->where('is_system', true)
                ->where('name', 'Oszczędności'))
            ->orderBy('id')
            ->get();

        /** @var Collection<int, Collection<int, CategoryAnnualEstimate>> $byUser */
        $byUser = $estimates->groupBy(fn (CategoryAnnualEstimate $estimate): int => $estimate->category->user_id);

        foreach ($byUser as $userId => $userEstimates) {
            if (Goal::query()->where('user_id', $userId)->exists()) {
                continue;
            }

            $goal = Goal::query()->create([
                'user_id' => $userId,
                'name' => self::DEFAULT_GOAL_NAME,
                'sort_order' => 10,
            ]);

            foreach ($userEstimates as $categoryEstimate) {
                $this->copyEstimates($goal, $categoryEstimate);
            }
        }
    }

    private function copyEstimates(Goal $goal, CategoryAnnualEstimate $categoryEstimate): void
    {
        $category = $categoryEstimate->category;

        GoalAnnualEstimate::query()->updateOrCreate(
            [
                'goal_id' => $goal->id,
                'year' => $categoryEstimate->year,
            ],
            [
                'amount' => $categoryEstimate->amount,
            ],
        );

        CategoryMonthlyEstimate::query()
            ->where('category_id', $category->id)
            ->where('year', $categoryEstimate->year)
            ->orderBy('id')
            ->each(function (CategoryMonthlyEstimate $monthly) use ($goal): void {
                GoalMonthlyEstimate::query()->updateOrCreate(
                    [
                        'goal_id' => $goal->id,
                        'year' => $monthly->year,
                        'month' => $monthly->month,
                    ],
                    [
                        'amount' => $monthly->amount,
                    ],
                );
            });
    }
}

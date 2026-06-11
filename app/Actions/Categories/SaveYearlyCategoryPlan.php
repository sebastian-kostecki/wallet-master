<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\YearlyMonthlyTemplate;
use App\Telemetry\Event;
use Illuminate\Support\Facades\DB;

final class SaveYearlyCategoryPlan
{
    /**
     * @param  array{year: int, annual_amount: numeric-string|float|int|null, monthly_amount?: numeric-string|float|int|null}  $validated
     */
    public function handle(Category $category, array $validated): CategoryAnnualEstimate
    {
        return DB::transaction(function () use ($category, $validated): CategoryAnnualEstimate {
            $year = $validated['year'];

            $annual = CategoryAnnualEstimate::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'year' => $year,
                ],
                [
                    'amount' => $validated['annual_amount'] ?? null,
                ],
            );

            $monthlyAmount = $validated['monthly_amount'] ?? null;

            if ($monthlyAmount !== null) {
                $existingMonthly = CategoryMonthlyEstimate::query()
                    ->where('category_id', $category->id)
                    ->where('year', $year)
                    ->get()
                    ->keyBy('month');

                foreach (YearlyMonthlyTemplate::eligibleMonths($year) as $month) {
                    $monthly = $existingMonthly->get($month);

                    if (YearlyMonthlyTemplate::hasExistingOverride($monthly)) {
                        continue;
                    }

                    CategoryMonthlyEstimate::query()->updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'year' => $year,
                            'month' => $month,
                        ],
                        [
                            'amount' => $monthlyAmount,
                        ],
                    );
                }
            }

            Event::record('category_estimate_yearly_plan_saved', [
                'category_id' => $category->id,
                'year' => $year,
            ], $category->user_id);

            return $annual;
        });
    }
}

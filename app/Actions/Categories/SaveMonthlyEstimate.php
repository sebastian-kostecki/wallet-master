<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\CategoryMonthlyEstimate;
use App\Telemetry\Event;

final class SaveMonthlyEstimate
{
    /**
     * @param  array{year: int, month: int, amount: ?numeric-string|float|int|null}  $validated
     */
    public function handle(Category $category, array $validated): CategoryMonthlyEstimate
    {
        $estimate = CategoryMonthlyEstimate::query()->updateOrCreate(
            [
                'category_id' => $category->id,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            [
                'amount' => $validated['amount'] ?? null,
            ],
        );

        Event::record('category_estimate_monthly_saved', [
            'category_id' => $category->id,
            'year' => $validated['year'],
            'month' => $validated['month'],
        ], $category->user_id);

        return $estimate;
    }
}

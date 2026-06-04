<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Telemetry\Event;

final class SaveAnnualEstimate
{
    /**
     * @param  array{year: int, amount: ?numeric-string|float|int|null}  $validated
     */
    public function handle(Category $category, array $validated): CategoryAnnualEstimate
    {
        $estimate = CategoryAnnualEstimate::query()->updateOrCreate(
            [
                'category_id' => $category->id,
                'year' => $validated['year'],
            ],
            [
                'amount' => $validated['amount'] ?? null,
            ],
        );

        Event::record('category_estimate_annual_saved', [
            'category_id' => $category->id,
            'year' => $validated['year'],
        ], $category->user_id);

        return $estimate;
    }
}

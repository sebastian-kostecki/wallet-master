<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Enums\PocketPlanningMode;
use App\Models\CategoryAnnualEstimate;
use App\Models\Currency;
use App\Models\Pocket;
use App\Support\Categories\CategoryColors;
use Illuminate\Support\Collection;

final class MigrateLegacySavingsEstimate
{
    private const DEFAULT_POCKET_NAME = 'Oszczędności ogólne';

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
            if (Pocket::query()->where('user_id', $userId)->exists()) {
                continue;
            }

            $latestAnnual = $userEstimates
                ->filter(fn (CategoryAnnualEstimate $estimate): bool => $estimate->amount !== null)
                ->sortByDesc('year')
                ->first();

            $annualAmount = $latestAnnual?->amount !== null ? (string) $latestAnnual->amount : null;
            $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

            Pocket::query()->create([
                'user_id' => $userId,
                'name' => self::DEFAULT_POCKET_NAME,
                'icon' => 'piggy-bank',
                'color' => CategoryColors::values()[0],
                'sort_order' => 10,
                'currency_id' => $plnId,
                'target_amount' => $annualAmount,
                'planning_mode' => $annualAmount !== null ? PocketPlanningMode::Monthly : null,
                'monthly_contribution' => $annualAmount !== null ? bcdiv($annualAmount, '12', 2) : null,
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Goals;

use App\Http\Resources\Accounts\CurrencyResource;
use App\Models\Goal;
use App\Support\Goals\GoalBalance;
use App\Support\Goals\GoalPlanningProjection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Goal
 */
final class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $balanceData = $user !== null
            ? GoalBalance::cumulative($user, $this->resource)
            : ['saved_total' => '0.00', 'released_total' => '0.00', 'balance' => '0.00'];

        $balance = $balanceData['balance'];
        $monthlyNets = $user !== null ? GoalBalance::monthlyNetMap($user, $this->resource) : [];
        $projectedCompletion = GoalPlanningProjection::projectedCompletionDate($this->resource, $balance, $monthlyNets);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded(
                'currency',
                fn () => CurrencyResource::make($this->currency)->resolve($request),
            ),
            'target_amount' => $this->target_amount !== null ? (string) $this->target_amount : null,
            'planning_mode' => $this->planning_mode?->value,
            'monthly_contribution' => $this->monthly_contribution !== null ? (string) $this->monthly_contribution : null,
            'target_date' => $this->target_date?->toDateString(),
            'is_archived' => $this->is_archived,
            'is_completed' => GoalBalance::isCompleted($this->resource, $balance),
            'is_overdue' => GoalPlanningProjection::isOverdue($this->resource, $balance),
            'progress_percent' => GoalBalance::progressPercent($this->resource, $balance),
            'balance' => $balance,
            'saved_total' => $balanceData['saved_total'],
            'released_total' => $balanceData['released_total'],
            'recommended_monthly' => GoalPlanningProjection::recommendedMonthly($this->resource, $balance),
            'projected_completion_date' => $projectedCompletion?->toDateString(),
        ];
    }
}

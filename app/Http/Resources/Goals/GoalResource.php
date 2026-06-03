<?php

declare(strict_types=1);

namespace App\Http\Resources\Goals;

use App\Models\Goal;
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
        $annualEstimate = $this->relationLoaded('annualEstimates')
            ? $this->annualEstimates->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'annual_estimate_amount' => $annualEstimate?->amount !== null
                ? (string) $annualEstimate->amount
                : null,
        ];
    }
}

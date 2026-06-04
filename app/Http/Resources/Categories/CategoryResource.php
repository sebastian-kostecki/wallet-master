<?php

declare(strict_types=1);

namespace App\Http\Resources\Categories;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
final class CategoryResource extends JsonResource
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
            'icon' => $this->icon,
            'color' => $this->color,
            'type' => $this->type->value,
            'type_label_key' => $this->type->labelKey(),
            'sort_order' => $this->sort_order,
            'is_system' => $this->is_system,
            'annual_estimate_amount' => $this->when(
                $this->relationLoaded('annualEstimates'),
                $annualEstimate?->amount !== null ? (string) $annualEstimate->amount : null,
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Account $resource
 */
final class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'currency_id' => $this->resource->currency_id,
            'bank' => $this->resource->bank?->value,
            'bank_icon_url' => $this->resource->bank_icon_url,
            'type' => $this->resource->type?->value,
            'type_label_key' => $this->resource->type_label_key,
            'current_balance' => $this->when(isset($this->resource->current_balance), $this->resource->current_balance),
            'opening_balance' => $this->when(isset($this->resource->opening_balance), $this->resource->opening_balance),
            'is_deleted' => $this->resource->trashed(),
            'currency' => $this->when(
                $this->resource->relationLoaded('currency'),
                fn () => $this->resource->currency !== null
                    ? CurrencyResource::make($this->resource->currency)->resolve($request)
                    : null,
            ),
        ];
    }
}

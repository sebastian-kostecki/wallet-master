<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounts;

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
            'bank' => $this->whenHas('bank', fn () => $this->resource->bank?->value),
            'bank_icon_url' => $this->whenHas('bank', fn () => $this->resource->bank?->bankIconUrl()),
            'type' => $this->whenHas('type', fn () => $this->resource->type?->value),
            'type_label_key' => $this->whenHas('type', fn () => $this->resource->type?->labelKey() ?? ''),
            'current_balance' => $this->whenHas('current_balance'),
            'opening_balance' => $this->whenHas('opening_balance'),
            'is_deleted' => $this->whenHas('deleted_at', fn () => $this->resource->trashed(), false),
            'currency' => $this->when(
                $this->resource->relationLoaded('currency'),
                fn () => $this->resource->currency !== null
                    ? CurrencyResource::make($this->resource->currency)->resolve($request)
                    : null,
            ),
        ];
    }
}

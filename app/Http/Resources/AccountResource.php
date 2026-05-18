<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
final class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency_id' => $this->currency_id,
            'bank' => $this->bank?->value,
            'bank_icon_url' => $this->bank_icon_url,
            'type' => $this->type?->value,
            'type_label_key' => $this->type_label_key,
            'current_balance' => $this->when(isset($this->current_balance), $this->current_balance),
            'opening_balance' => $this->when(isset($this->opening_balance), $this->opening_balance),
            'is_deleted' => $this->trashed(),
            'currency' => $this->when(
                $this->relationLoaded('currency'),
                fn () => $this->currency !== null
                    ? CurrencyResource::make($this->currency)->resolve($request)
                    : null,
            ),
        ];
    }
}

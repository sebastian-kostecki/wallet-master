<?php

declare(strict_types=1);

namespace App\Http\Resources\Deposits;

use App\Enums\DepositMovementKind;
use App\Http\Resources\Accounts\CurrencyResource;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deposit
 */
final class DepositResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'principal' => $this->principal,
            'opened_at' => $this->opened_at->toDateString(),
            'matures_at' => $this->matures_at?->toDateString(),
            'status' => $this->status->value,
            'closed_at' => $this->closed_at?->toDateString(),
            'source_account_id' => $this->source_account_id,
            'currency' => $this->whenLoaded(
                'currency',
                fn () => CurrencyResource::make($this->currency)->resolve($request),
            ),
            'has_interest' => $this->whenLoaded(
                'movements',
                fn () => $this->movements->contains(
                    fn ($movement) => $movement->kind === DepositMovementKind::Interest,
                ),
                false,
            ),
        ];
    }
}

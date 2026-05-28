<?php

declare(strict_types=1);

namespace App\Http\Resources\Transactions;

use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Accounts\CurrencyResource;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDateRelative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateIso = $this->date->toDateString();
        $bookedAtIso = $this->booked_at?->toDateString() ?? $dateIso;

        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'currency_id' => $this->currency_id,
            'date' => $dateIso,
            'booked_at' => $bookedAtIso,
            'date_relative' => TransactionDateRelative::format($this->resource),
            'amount' => $this->amount,
            'type' => $this->type->value,
            'description' => $this->description,
            'subject' => $this->subject,
            'transfer_id' => $this->transfer_id,
            'account' => $this->account !== null
                ? AccountResource::make($this->account)->resolve($request)
                : null,
            'currency' => $this->currency !== null
                ? CurrencyResource::make($this->currency)->resolve($request)
                : null,
        ];
    }
}

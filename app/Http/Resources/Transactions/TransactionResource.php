<?php

declare(strict_types=1);

namespace App\Http\Resources\Transactions;

use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Accounts\CurrencyResource;
use App\Http\Resources\Categories\CategoryResource;
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
        $bookedAtIso = $this->booked_at !== null
            ? $this->booked_at->toDateString()
            : $dateIso;

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
            'raw_statement_description' => $this->raw_statement_description,
            'transfer_id' => $this->transfer_id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->category !== null
                    ? CategoryResource::make($this->category)->resolve($request)
                    : null,
            ),
            'account' => $this->whenLoaded(
                'account',
                fn () => $this->account !== null
                    ? AccountResource::make($this->account)->resolve($request)
                    : null,
            ),
            'currency' => $this->whenLoaded(
                'currency',
                fn () => $this->currency !== null
                    ? CurrencyResource::make($this->currency)->resolve($request)
                    : null,
            ),
        ];
    }
}

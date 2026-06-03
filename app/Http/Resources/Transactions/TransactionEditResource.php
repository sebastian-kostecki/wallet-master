<?php

declare(strict_types=1);

namespace App\Http\Resources\Transactions;

use App\Http\Resources\Accounts\CurrencyResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionEditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'date' => $this->date->toDateString(),
            'booked_at' => $this->booked_at?->toDateString(),
            'amount' => $this->amount,
            'type' => $this->type->value,
            'description' => $this->description,
            'subject' => $this->subject,
            'import_id' => $this->import_id,
            'raw_statement_description' => $this->raw_statement_description,
            'transfer_id' => $this->transfer_id,
            'account' => $this->account !== null
                ? $this->account->only(['id', 'name'])
                : null,
            'currency' => $this->currency !== null
                ? CurrencyResource::make($this->currency)->resolve($request)
                : null,
        ];
    }
}

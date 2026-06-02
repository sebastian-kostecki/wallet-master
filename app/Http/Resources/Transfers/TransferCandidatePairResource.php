<?php

declare(strict_types=1);

namespace App\Http\Resources\Transfers;

use App\Http\Resources\Accounts\AccountResource;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransferCandidatePairResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Transaction $anchor */
        $anchor = $this->resource;
        /** @var Transaction $partner */
        $partner = $anchor->transferCandidate;

        $negativeLeg = bccomp((string) $anchor->amount, '0', 2) < 0 ? $anchor : $partner;
        $positiveLeg = bccomp((string) $anchor->amount, '0', 2) > 0 ? $anchor : $partner;

        $negativeLeg->loadMissing(['account.currency']);
        $positiveLeg->loadMissing(['account.currency']);

        $amount = ltrim(TransactionDedupe::amountToDecimalString((string) $negativeLeg->amount), '-');

        $negativeDate = CarbonImmutable::parse($negativeLeg->date->toDateString());
        $positiveDate = CarbonImmutable::parse($positiveLeg->date->toDateString());

        return [
            'anchor_transaction_id' => $anchor->id,
            'amount' => $amount,
            'date_delta_days' => (int) $negativeDate->diffInDays($positiveDate),
            'from_account' => new AccountResource($negativeLeg->account)->resolve(),
            'to_account' => new AccountResource($positiveLeg->account)->resolve(),
            'from_transaction' => $this->transactionPayload($negativeLeg),
            'to_transaction' => $this->transactionPayload($positiveLeg),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'date' => $transaction->date->format('d-m-Y'),
            'booked_at' => ($transaction->booked_at ?? $transaction->date)->format('d-m-Y'),
            'amount' => TransactionDedupe::amountToDecimalString((string) $transaction->amount),
            'description' => $transaction->description,
        ];
    }
}

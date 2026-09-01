<?php

declare(strict_types=1);

namespace App\Http\Resources\Deposits;

use App\Http\Resources\Accounts\AccountResource;
use App\Models\Transaction;
use App\Support\Deposits\DepositClassificationIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class DepositCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Transaction $transaction */
        $transaction = $this->resource;

        $transaction->loadMissing(['account.currency']);

        return [
            'transaction_id' => $transaction->id,
            'kind' => $transaction->deposit_candidate_kind,
            'amount' => (string) $transaction->amount,
            'date' => ($transaction->booked_at ?? $transaction->date)->format('d-m-Y'),
            'description' => $transaction->description,
            'account' => AccountResource::make($transaction->account)->resolve($request),
            'matching_deposits' => $this->when(
                $transaction->deposit_candidate_kind === DepositClassificationIntent::ClosePrincipal->value,
                fn () => DepositResource::collection(
                    $transaction->relationLoaded('matchingDeposits')
                        ? $transaction->getRelation('matchingDeposits')
                        : collect(),
                )->resolve($request),
            ),
        ];
    }
}

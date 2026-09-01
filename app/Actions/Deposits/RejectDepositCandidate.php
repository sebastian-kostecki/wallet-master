<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositMatchStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RejectDepositCandidate
{
    public function handle(User $user, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $transaction): void {
            /** @var Transaction $anchor */
            $anchor = Transaction::query()
                ->whereKey($transaction->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($anchor->deposit_match_status !== DepositMatchStatus::Manual) {
                abort(422, 'Transaction is not a pending deposit candidate.');
            }

            if ($anchor->deposit_candidate_kind === null) {
                abort(422, 'Transaction has no deposit candidate.');
            }

            $anchor->update([
                'deposit_match_status' => DepositMatchStatus::Rejected,
                'deposit_candidate_kind' => null,
            ]);
        });
    }
}

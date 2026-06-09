<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Enums\TransferMatchStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RejectTransferCandidate
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

            if ($anchor->transfer_match_status !== TransferMatchStatus::Manual) {
                abort(422, 'Transaction is not a pending transfer candidate.');
            }

            $partnerId = $anchor->transfer_candidate_for_id;

            if ($partnerId === null) {
                abort(422, 'Transaction has no transfer candidate.');
            }

            /** @var Transaction $partner */
            $partner = Transaction::query()
                ->whereKey($partnerId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            foreach ([$anchor, $partner] as $leg) {
                $leg->update([
                    'transfer_match_status' => TransferMatchStatus::Rejected,
                    'transfer_candidate_for_id' => null,
                ]);
            }
        });
    }
}

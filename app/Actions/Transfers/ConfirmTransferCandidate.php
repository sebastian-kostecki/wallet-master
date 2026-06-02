<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ConfirmTransferCandidate
{
    public function handle(User $user, Transaction $transaction): string
    {
        return DB::transaction(function () use ($user, $transaction): string {
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

            if ($partner->transfer_candidate_for_id !== $anchor->id) {
                abort(422, 'Transfer candidate link is not reciprocal.');
            }

            if ($anchor->transfer_id !== null || $partner->transfer_id !== null) {
                abort(422, 'Transfer is already linked.');
            }

            $transferId = (string) Str::uuid();

            foreach ([$anchor, $partner] as $leg) {
                $leg->update([
                    'transfer_id' => $transferId,
                    'type' => TransactionType::Transfer,
                    'transfer_match_status' => TransferMatchStatus::Manual,
                    'transfer_candidate_for_id' => null,
                ]);
            }

            Log::channel('telemetry')->info('transfer_manually_linked', [
                'event' => 'transfer_manually_linked',
                'user_id' => $user->id,
                'transfer_id' => $transferId,
                'transaction_ids' => [$anchor->id, $partner->id],
            ]);

            return $transferId;
        });
    }
}

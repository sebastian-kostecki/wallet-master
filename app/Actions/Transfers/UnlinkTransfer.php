<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Telemetry\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class UnlinkTransfer
{
    public function handle(User $user, string $transferId): void
    {
        DB::transaction(function () use ($user, $transferId): void {
            /** @var Collection<int, Transaction> $transactions */
            $transactions = Transaction::query()
                ->where('user_id', $user->id)
                ->where('transfer_id', $transferId)
                ->lockForUpdate()
                ->get();

            if ($transactions->count() !== 2) {
                abort(409, 'Transfer is incomplete and cannot be unlinked.');
            }

            foreach ($transactions as $transaction) {
                $transaction->update([
                    'transfer_id' => null,
                    'type' => TransactionType::fromAmount((string) $transaction->amount),
                    'transfer_match_status' => TransferMatchStatus::Rejected,
                    'transfer_candidate_for_id' => null,
                ]);
            }

            Event::record('transfer_unlinked', [
                'transfer_id' => $transferId,
                'transaction_ids' => $transactions->pluck('id')->all(),
            ], $user->id);
        });
    }
}

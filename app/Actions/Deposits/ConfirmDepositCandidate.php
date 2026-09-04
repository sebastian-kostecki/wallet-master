<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositMatchStatus;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Telemetry\Event;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ConfirmDepositCandidate
{
    public function __construct(
        private readonly AssignTransactionToDeposit $assignTransactionToDeposit,
    ) {}

    /**
     * @param  array{
     *   deposit_id?: int,
     *   name?: ?string,
     *   matures_at?: ?string,
     * }  $validated
     *
     * @throws Throwable
     */
    public function handle(User $user, Transaction $transaction, array $validated): Deposit
    {
        return DB::transaction(function () use ($user, $transaction, $validated): Deposit {
            /** @var Transaction $anchor */
            $anchor = Transaction::query()
                ->whereKey($transaction->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($anchor->deposit_match_status !== DepositMatchStatus::Manual) {
                abort(422, 'Transaction is not a pending deposit candidate.');
            }

            $candidateKind = $anchor->deposit_candidate_kind;

            if ($candidateKind === null) {
                abort(422, 'Transaction has no deposit candidate.');
            }

            $assignPayload = match ($candidateKind) {
                'open' => [
                    'kind' => 'open',
                    'name' => $validated['name'] ?? null,
                    'matures_at' => $validated['matures_at'] ?? null,
                ],
                'close_principal' => [
                    'kind' => 'close_principal',
                    'deposit_id' => $validated['deposit_id'] ?? null,
                ],
                default => abort(422, 'Unknown deposit candidate kind.'),
            };

            $deposit = $this->assignTransactionToDeposit->handle($user, $anchor, $assignPayload);

            $anchor->update([
                'deposit_candidate_kind' => null,
            ]);

            $eventName = $candidateKind === 'open'
                ? 'deposit_import_open_confirmed'
                : 'deposit_import_close_confirmed';

            Event::record($eventName, [
                'deposit_id' => $deposit->id,
                'transaction_id' => $anchor->id,
                'kind' => $candidateKind,
            ], $user->id);

            return $deposit;
        });
    }
}

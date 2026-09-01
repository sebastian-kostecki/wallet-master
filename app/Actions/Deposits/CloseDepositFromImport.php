<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Telemetry\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CloseDepositFromImport
{
    /**
     * @throws Throwable
     */
    public function handle(User $user, Deposit $deposit, Transaction $transaction): Deposit
    {
        $deposit = DB::transaction(function () use ($user, $deposit, $transaction): Deposit {
            $transaction = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $deposit = Deposit::query()
                ->whereKey($deposit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEligible($user, $deposit, $transaction);

            $amount = (string) $transaction->amount;

            if (bccomp($amount, '0', 2) !== 1) {
                throw ValidationException::withMessages([
                    'transaction' => 'Close principal assignments require a positive amount.',
                ]);
            }

            if (bccomp($amount, (string) $deposit->principal, 2) !== 0) {
                throw ValidationException::withMessages([
                    'transaction' => 'The transaction amount must equal the deposit principal.',
                ]);
            }

            $transaction->category_id = null;
            $transaction->deposit_match_status = DepositMatchStatus::Auto;
            $transaction->deposit_candidate_kind = null;
            $transaction->save();

            DepositMovement::query()->create([
                'deposit_id' => $deposit->id,
                'transaction_id' => $transaction->id,
                'kind' => DepositMovementKind::ClosePrincipal,
            ]);

            $deposit->status = DepositStatus::Closed;
            $deposit->closed_at = $transaction->date->toDateString();
            $deposit->save();

            return $deposit;
        }, attempts: 5);

        Event::record('deposit_closed', [
            'deposit_id' => $deposit->id,
            'transaction_id' => $transaction->id,
        ], $user->id);

        return $deposit;
    }

    private function assertEligible(User $user, Deposit $deposit, Transaction $transaction): void
    {
        if ($deposit->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'deposit' => 'The selected deposit does not belong to you.',
            ]);
        }

        if ($transaction->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'transaction' => 'You can only close deposits with your own transactions.',
            ]);
        }

        if ($deposit->currency_id !== $transaction->currency_id) {
            throw ValidationException::withMessages([
                'deposit' => 'The deposit currency must match the transaction currency.',
            ]);
        }

        if ($deposit->status !== DepositStatus::Active) {
            throw ValidationException::withMessages([
                'deposit' => 'Only active deposits can receive a close principal assignment.',
            ]);
        }

        if ($transaction->transfer_id !== null) {
            throw ValidationException::withMessages([
                'transaction' => 'Transfer-linked transactions cannot be assigned to deposits.',
            ]);
        }

        if ($transaction->depositMovement()->exists()) {
            throw ValidationException::withMessages([
                'transaction' => 'This transaction is already linked to a deposit.',
            ]);
        }

        if ($transaction->type === TransactionType::Adjustment) {
            throw ValidationException::withMessages([
                'transaction' => 'Adjustment transactions cannot be assigned to deposits.',
            ]);
        }
    }
}

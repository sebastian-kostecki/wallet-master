<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\Bank;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Telemetry\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AssignTransactionToDeposit
{
    /**
     * @param  array{
     *   kind: 'open'|'close_principal'|'interest',
     *   deposit_id?: int,
     *   name?: ?string,
     *   matures_at?: ?string,
     * }  $validated
     *
     * @throws Throwable
     */
    public function handle(User $user, Transaction $transaction, array $validated): Deposit
    {
        $kind = DepositMovementKind::from($validated['kind']);

        $deposit = DB::transaction(function () use ($user, $transaction, $validated, $kind): Deposit {
            $transaction = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction->load('account');
            $this->assertEligible($user, $transaction);

            return match ($kind) {
                DepositMovementKind::Open => $this->assignOpen($user, $transaction, $validated),
                DepositMovementKind::ClosePrincipal => $this->assignClosePrincipal($user, $transaction, $validated),
                DepositMovementKind::Interest => $this->assignInterest($user, $transaction, $validated),
            };
        }, attempts: 5);

        Event::record('deposit_transaction_assigned', [
            'deposit_id' => $deposit->id,
            'transaction_id' => $transaction->id,
            'kind' => $kind->value,
        ], $user->id);

        return $deposit;
    }

    private function assertEligible(User $user, Transaction $transaction): void
    {
        if ($transaction->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'transaction' => 'You can only assign your own transactions to deposits.',
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

        $account = $transaction->account;

        if ($account === null || $account->trashed()) {
            throw ValidationException::withMessages([
                'transaction' => 'Transactions on deleted accounts cannot be assigned to deposits.',
            ]);
        }

        if ($account->bank === Bank::Cash) {
            throw ValidationException::withMessages([
                'transaction' => 'Cash wallet transactions cannot be assigned to deposits.',
            ]);
        }
    }

    /**
     * @param  array{name?: ?string, matures_at?: ?string}  $validated
     */
    private function assignOpen(User $user, Transaction $transaction, array $validated): Deposit
    {
        $amount = (string) $transaction->amount;

        if (bccomp($amount, '0', 2) !== -1) {
            throw ValidationException::withMessages([
                'transaction' => 'Opening a deposit requires a negative transaction amount.',
            ]);
        }

        $account = $transaction->account;
        $principal = bcmul($amount, '-1', 2);
        $openedAt = $transaction->date->toDateString();

        $name = isset($validated['name']) ? trim((string) $validated['name']) : null;
        $name = $name === '' ? null : $name;

        $maturesAt = ! empty($validated['matures_at'])
            ? CarbonImmutable::createFromFormat('d-m-Y', $validated['matures_at'])->toDateString()
            : null;

        $deposit = Deposit::query()->create([
            'user_id' => $user->id,
            'currency_id' => $transaction->currency_id,
            'source_account_id' => $account->id,
            'name' => $name,
            'principal' => $principal,
            'opened_at' => $openedAt,
            'matures_at' => $maturesAt,
            'status' => DepositStatus::Active,
            'closed_at' => null,
        ]);

        $transaction->category_id = null;
        $transaction->save();

        DepositMovement::query()->create([
            'deposit_id' => $deposit->id,
            'transaction_id' => $transaction->id,
            'kind' => DepositMovementKind::Open,
        ]);

        return $deposit;
    }

    /**
     * @param  array{deposit_id?: int}  $validated
     */
    private function assignClosePrincipal(User $user, Transaction $transaction, array $validated): Deposit
    {
        if (! isset($validated['deposit_id'])) {
            throw ValidationException::withMessages([
                'deposit_id' => 'A deposit is required to assign a close principal transaction.',
            ]);
        }

        $deposit = Deposit::query()
            ->whereKey($validated['deposit_id'])
            ->lockForUpdate()
            ->first();

        if ($deposit === null || $deposit->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'deposit_id' => 'The selected deposit does not belong to you.',
            ]);
        }

        if ($deposit->currency_id !== $transaction->currency_id) {
            throw ValidationException::withMessages([
                'deposit_id' => 'The deposit currency must match the transaction currency.',
            ]);
        }

        if ($deposit->status !== DepositStatus::Active) {
            throw ValidationException::withMessages([
                'deposit_id' => 'Only active deposits can receive a close principal assignment.',
            ]);
        }

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
    }

    /**
     * @param  array{deposit_id?: int}  $validated
     */
    private function assignInterest(User $user, Transaction $transaction, array $validated): Deposit
    {
        if (! isset($validated['deposit_id'])) {
            throw ValidationException::withMessages([
                'deposit_id' => 'A deposit is required to assign an interest transaction.',
            ]);
        }

        $deposit = Deposit::query()
            ->whereKey($validated['deposit_id'])
            ->lockForUpdate()
            ->first();

        if ($deposit === null || $deposit->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'deposit_id' => 'The selected deposit does not belong to you.',
            ]);
        }

        if ($deposit->currency_id !== $transaction->currency_id) {
            throw ValidationException::withMessages([
                'deposit_id' => 'The deposit currency must match the transaction currency.',
            ]);
        }

        if (! in_array($deposit->status, [DepositStatus::Active, DepositStatus::Closed], true)) {
            throw ValidationException::withMessages([
                'deposit_id' => 'The selected deposit cannot receive an interest assignment.',
            ]);
        }

        $amount = (string) $transaction->amount;

        if (bccomp($amount, '0', 2) !== 1) {
            throw ValidationException::withMessages([
                'transaction' => 'Interest assignments require a positive amount.',
            ]);
        }

        $hasInterestMovement = $deposit->movements()
            ->where('kind', DepositMovementKind::Interest)
            ->exists();

        if ($hasInterestMovement) {
            throw ValidationException::withMessages([
                'deposit_id' => 'This deposit already has an interest transaction assigned.',
            ]);
        }

        DepositMovement::query()->create([
            'deposit_id' => $deposit->id,
            'transaction_id' => $transaction->id,
            'kind' => DepositMovementKind::Interest,
        ]);

        return $deposit;
    }
}

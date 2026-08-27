<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\Bank;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use App\Telemetry\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateDeposit
{
    /**
     * @param  array{
     *   source_account_id: int,
     *   principal: numeric-string|float|int,
     *   opened_at: string,
     *   name?: ?string,
     *   matures_at?: ?string,
     * }  $validated
     *
     * @throws Throwable
     */
    public function handle(User $user, array $validated): Deposit
    {
        $deposit = DB::transaction(function () use ($user, $validated): Deposit {
            $account = Account::query()
                ->whereKey($validated['source_account_id'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($account === null || $account->trashed()) {
                abort(403);
            }

            if ($account->bank === Bank::Cash) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Cash wallets cannot be used as a deposit source account.',
                ]);
            }

            $openedAt = CarbonImmutable::createFromFormat('d-m-Y', $validated['opened_at'])->toDateString();
            $maturesAt = ! empty($validated['matures_at'])
                ? CarbonImmutable::createFromFormat('d-m-Y', $validated['matures_at'])->toDateString()
                : null;

            $principal = TransactionDedupe::amountToDecimalString($validated['principal']);
            $amount = bcmul($principal, '-1', 2);

            try {
                $transactionType = TransactionType::fromAmount($amount);
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['principal' => $e->getMessage()]);
            }

            $name = isset($validated['name']) ? trim($validated['name']) : null;
            $name = $name === '' ? null : $name;

            $description = $name ?? 'Term deposit';
            $normalizedDescription = TransactionDedupe::normalizeDescription($description);
            $dedupeHash = TransactionDedupe::manualDedupeHash($openedAt, $amount, $normalizedDescription);

            $deposit = Deposit::query()->create([
                'user_id' => $user->id,
                'currency_id' => $account->currency_id,
                'source_account_id' => $account->id,
                'name' => $name,
                'principal' => $principal,
                'opened_at' => $openedAt,
                'matures_at' => $maturesAt,
                'status' => DepositStatus::Active,
                'closed_at' => null,
            ]);

            $transaction = Transaction::withoutEvents(function () use ($user, $account, $openedAt, $amount, $transactionType, $description, $normalizedDescription, $dedupeHash): Transaction {
                return Transaction::query()->create([
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'currency_id' => $account->currency_id,
                    'date' => $openedAt,
                    'booked_at' => $openedAt,
                    'amount' => $amount,
                    'type' => $transactionType,
                    'description' => $description,
                    'subject' => null,
                    'normalized_description' => $normalizedDescription,
                    'dedupe_hash' => $dedupeHash,
                    'category_id' => null,
                ]);
            });

            DepositMovement::query()->create([
                'deposit_id' => $deposit->id,
                'transaction_id' => $transaction->id,
                'kind' => DepositMovementKind::Open,
            ]);

            $account->current_balance = bcadd((string) $account->current_balance, $amount, 2);
            $account->save();

            return $deposit;
        }, attempts: 5);

        Event::record('deposit_created', [
            'deposit_id' => $deposit->id,
        ], $user->id);

        return $deposit;
    }
}

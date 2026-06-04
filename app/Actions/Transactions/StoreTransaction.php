<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use App\Telemetry\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StoreTransaction
{
    /**
     * @param  array{
     *   account_id: int,
     *   date: string,
     *   booked_at?: ?string,
     *   amount: numeric-string|float|int,
     *   description: string,
     *   subject?: ?string,
     *   category_id: int,
     *   goal_id?: ?int,
     * }  $validated
     *
     * @throws Throwable
     */
    public function handle(User $user, array $validated): Transaction
    {
        $transaction = DB::transaction(function () use ($user, $validated): Transaction {
            $account = Account::query()
                ->whereKey($validated['account_id'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $date = CarbonImmutable::createFromFormat('d-m-Y', $validated['date'])->toDateString();
            $bookedAt = ! empty($validated['booked_at'])
                ? CarbonImmutable::createFromFormat('d-m-Y', $validated['booked_at'])->toDateString()
                : $date;
            $amount = TransactionDedupe::amountToDecimalString($validated['amount']);

            try {
                $transactionType = TransactionType::fromAmount($amount);
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['amount' => $e->getMessage()]);
            }

            $normalizedDescription = TransactionDedupe::normalizeDescription($validated['description']);
            $dedupeHash = TransactionDedupe::manualDedupeHash($bookedAt, $amount, $normalizedDescription);

            $goalId = array_key_exists('goal_id', $validated) && $validated['goal_id'] !== null
                ? (int) $validated['goal_id']
                : null;

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'currency_id' => $account->currency_id,
                'date' => $date,
                'booked_at' => $bookedAt,
                'amount' => $amount,
                'type' => $transactionType,
                'description' => $validated['description'],
                'subject' => $validated['subject'] ?? null,
                'normalized_description' => $normalizedDescription,
                'dedupe_hash' => $dedupeHash,
                'category_id' => $validated['category_id'],
                'goal_id' => $goalId,
            ]);

            $account->current_balance = bcadd((string) $account->current_balance, $amount, 2);
            $account->save();

            return $transaction;
        });

        Event::record('transaction_created', [
            'transaction_id' => $transaction->id,
            'account_id' => $transaction->account_id,
        ], $user->id);

        return $transaction;
    }
}

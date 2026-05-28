<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreTransaction
{
    /**
     * @param array{
     *   account_id: int,
     *   date: string,
     *   booked_at?: ?string,
     *   amount: numeric-string|float|int,
     *   description: string,
     *   subject?: ?string,
     * } $validated
     *
     * @throws \Throwable
     */
    public function handle(User $user, array $validated): Transaction
    {
        return DB::transaction(function () use ($user, $validated): Transaction {
            $account = Account::query()
                ->whereKey($validated['account_id'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $date = CarbonImmutable::createFromFormat('d-m-Y', $validated['date'])->toDateString();
            $bookedAt = isset($validated['booked_at']) && is_string($validated['booked_at']) && $validated['booked_at'] !== ''
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
            ]);

            $account->current_balance = bcadd((string) $account->current_balance, $amount, 2);
            $account->save();

            return $transaction;
        });
    }
}

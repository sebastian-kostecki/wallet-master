<?php

namespace App\Actions\Transactions;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StoreTransaction
{
    /**
     * @param array{
     *   account_id: int,
     *   date: string,
     *   amount: numeric-string|float|int,
     *   description: string,
     *   subject?: ?string,
     * } $validated
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
            $amount = TransactionDedupe::amountToDecimalString($validated['amount']);
            $normalizedDescription = TransactionDedupe::normalizeDescription($validated['description']);
            $dedupeHash = TransactionDedupe::dedupeHash($date, $amount, $normalizedDescription);

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'currency_id' => $account->currency_id,
                'date' => $date,
                'amount' => $amount,
                'type' => ((float) $amount) < 0 ? 'expense' : 'income',
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

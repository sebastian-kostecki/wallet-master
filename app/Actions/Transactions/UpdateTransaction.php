<?php

namespace App\Actions\Transactions;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class UpdateTransaction
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
    public function handle(Transaction $transaction, array $validated): void
    {
        DB::transaction(function () use ($transaction, $validated): void {
            $date = CarbonImmutable::createFromFormat('d-m-Y', $validated['date'])->toDateString();
            $newAmount = TransactionDedupe::amountToDecimalString($validated['amount']);
            $normalizedDescription = TransactionDedupe::normalizeDescription($validated['description']);
            $dedupeHash = TransactionDedupe::dedupeHash($date, $newAmount, $normalizedDescription);

            $oldAmount = TransactionDedupe::amountToDecimalString((string) $transaction->amount);
            $oldAccountId = (int) $transaction->account_id;
            $newAccountId = (int) $validated['account_id'];

            if ($oldAccountId === $newAccountId) {
                $account = Account::query()
                    ->whereKey($oldAccountId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $delta = bcsub($newAmount, $oldAmount, 2);

                $transaction->account_id = $newAccountId;
                $transaction->currency_id = $account->currency_id;
                $transaction->date = $date;
                $transaction->amount = $newAmount;
                $transaction->type = ((float) $newAmount) < 0 ? 'expense' : 'income';
                $transaction->description = $validated['description'];
                $transaction->subject = $validated['subject'] ?? null;
                $transaction->normalized_description = $normalizedDescription;
                $transaction->dedupe_hash = $dedupeHash;
                $transaction->save();

                $account->current_balance = bcadd((string) $account->current_balance, $delta, 2);
                $account->save();

                return;
            }

            $accountIds = [$oldAccountId, $newAccountId];
            sort($accountIds);

            /** @var array<int, Account> $locked */
            $locked = Account::query()
                ->whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            $oldAccount = $locked[$oldAccountId] ?? null;
            $newAccount = $locked[$newAccountId] ?? null;

            if ($oldAccount === null || $newAccount === null) {
                abort(404);
            }

            $transaction->account_id = $newAccountId;
            $transaction->currency_id = $newAccount->currency_id;
            $transaction->date = $date;
            $transaction->amount = $newAmount;
            $transaction->type = ((float) $newAmount) < 0 ? 'expense' : 'income';
            $transaction->description = $validated['description'];
            $transaction->subject = $validated['subject'] ?? null;
            $transaction->normalized_description = $normalizedDescription;
            $transaction->dedupe_hash = $dedupeHash;
            $transaction->save();

            $oldAccount->current_balance = bcsub((string) $oldAccount->current_balance, $oldAmount, 2);
            $oldAccount->save();

            $newAccount->current_balance = bcadd((string) $newAccount->current_balance, $newAmount, 2);
            $newAccount->save();
        });
    }
}


<?php

namespace App\Actions\Transactions;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Support\Facades\DB;

final class DeleteTransaction
{
    public function handle(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $account = Account::query()
                ->whereKey($transaction->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = TransactionDedupe::amountToDecimalString((string) $transaction->amount);

            $transaction->delete();

            $account->current_balance = bcsub((string) $account->current_balance, $amount, 2);
            $account->save();
        });
    }
}

<?php

namespace App\Actions\Transactions;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class DeleteTransaction
{
    public function handle(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $transferId = $transaction->transfer_id;

            if ($transferId !== null && $transferId !== '') {
                $this->deleteTransfer($transaction);

                return;
            }

            $account = Account::query()
                ->whereKey($transaction->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->trashed()) {
                abort(403);
            }

            $amount = TransactionDedupe::amountToDecimalString((string) $transaction->amount);

            $transaction->delete();

            $account->current_balance = bcsub((string) $account->current_balance, $amount, 2);
            $account->save();
        });
    }

    private function deleteTransfer(Transaction $transaction): void
    {
        $transferId = (string) $transaction->transfer_id;

        /** @var Collection<int, Transaction> $transactions */
        $transactions = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('transfer_id', $transferId)
            ->lockForUpdate()
            ->get();

        if ($transactions->count() !== 2) {
            abort(409, 'Transfer is incomplete and cannot be deleted.');
        }

        $accountIds = $transactions
            ->pluck('account_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($accountIds as $accountId) {
            $account = $accounts->get($accountId);

            if ($account === null) {
                abort(409, 'Transfer account is missing and cannot be deleted.');
            }

            if ($account->trashed()) {
                abort(403);
            }
        }

        foreach ($transactions as $tx) {
            $account = $accounts->get((int) $tx->account_id);
            if ($account === null) {
                abort(409, 'Transfer account is missing and cannot be deleted.');
            }

            $amount = TransactionDedupe::amountToDecimalString((string) $tx->amount);
            $tx->delete();

            $account->current_balance = bcsub((string) $account->current_balance, $amount, 2);
            $account->save();
        }
    }
}

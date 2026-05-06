<?php

namespace App\Actions\Transfers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTransfer
{
    /**
     * @param array{
     *   from_account_id: int,
     *   to_account_id: int,
     *   date: string,
     *   amount: numeric-string|float|int,
     *   description?: ?string,
     * } $validated
     * @return array{
     *   withdrawal: Transaction,
     *   deposit: Transaction,
     *   transfer_id: string,
     *   from_account_id: int,
     *   to_account_id: int,
     *   amount: string,
     *   date: string,
     * }
     */
    public function handle(User $user, array $validated): array
    {
        return DB::transaction(function () use ($user, $validated): array {
            $fromId = (int) $validated['from_account_id'];
            $toId = (int) $validated['to_account_id'];

            $accountIds = [$fromId, $toId];
            sort($accountIds);

            $accounts = Account::query()
                ->whereIn('id', $accountIds)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Account|null $from */
            $from = $accounts->get($fromId);
            /** @var Account|null $to */
            $to = $accounts->get($toId);

            if ($from === null || $to === null || $from->trashed() || $to->trashed()) {
                abort(403);
            }

            if ((int) $from->currency_id !== (int) $to->currency_id) {
                abort(422, 'Transfers between different currencies are not supported.');
            }

            $dateYmd = CarbonImmutable::createFromFormat('d-m-Y', $validated['date'])->toDateString();
            $amount = TransactionDedupe::amountToDecimalString($validated['amount']);

            $withdrawAmount = bcmul($amount, '-1', 2);
            $depositAmount = $amount;

            $transferId = (string) Str::uuid();

            $description = isset($validated['description']) && $validated['description'] !== ''
                ? $validated['description']
                : null;

            $withdrawDescription = $description ?? "Transfer to {$to->name}";
            $depositDescription = $description ?? "Transfer from {$from->name}";

            $withdrawNormalized = TransactionDedupe::normalizeDescription($withdrawDescription);
            $depositNormalized = TransactionDedupe::normalizeDescription($depositDescription);

            $withdrawDedupeHash = md5($transferId.'|withdrawal', true);
            $depositDedupeHash = md5($transferId.'|deposit', true);

            $withdrawal = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $from->id,
                'currency_id' => $from->currency_id,
                'date' => $dateYmd,
                'amount' => $withdrawAmount,
                'type' => 'expense',
                'description' => $withdrawDescription,
                'subject' => null,
                'normalized_description' => $withdrawNormalized,
                'dedupe_hash' => $withdrawDedupeHash,
                'transfer_id' => $transferId,
            ]);

            $deposit = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $to->id,
                'currency_id' => $to->currency_id,
                'date' => $dateYmd,
                'amount' => $depositAmount,
                'type' => 'income',
                'description' => $depositDescription,
                'subject' => null,
                'normalized_description' => $depositNormalized,
                'dedupe_hash' => $depositDedupeHash,
                'transfer_id' => $transferId,
            ]);

            $from->current_balance = bcadd((string) $from->current_balance, $withdrawAmount, 2);
            $to->current_balance = bcadd((string) $to->current_balance, $depositAmount, 2);

            $from->save();
            $to->save();

            return [
                'withdrawal' => $withdrawal,
                'deposit' => $deposit,
                'transfer_id' => $transferId,
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount' => $amount,
                'date' => $dateYmd,
            ];
        }, attempts: 5);
    }
}

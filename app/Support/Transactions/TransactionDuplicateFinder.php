<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

final class TransactionDuplicateFinder
{
    /**
     * @return list<array{
     *     key: array{date: string, amount: string, description: string},
     *     keep_id: int,
     *     duplicate_ids: list<int>,
     *     transactions: list<array{
     *         id: int,
     *         account_id: int,
     *         user_id: int,
     *         import_id: int|null,
     *         transfer_id: string|null,
     *     }>
     * }>
     */
    public function findGroups(): array
    {
        /** @var array<string, list<array{
         *     id: int,
         *     account_id: int,
         *     user_id: int,
         *     import_id: int|null,
         *     transfer_id: string|null,
         *     date: string,
         *     amount: string,
         *     description: string,
         * }>> $buckets
         */
        $buckets = [];

        Transaction::query()
            ->orderBy('id')
            ->select([
                'id',
                'account_id',
                'user_id',
                'import_id',
                'transfer_id',
                'date',
                'amount',
                'description',
                'normalized_description',
            ])
            ->chunkById(500, function ($transactions) use (&$buckets): void {
                foreach ($transactions as $transaction) {
                    $logicalDescription = trim((string) $transaction->normalized_description) !== ''
                        ? (string) $transaction->normalized_description
                        : TransactionDedupe::normalizeDescription((string) $transaction->description);

                    $date = Carbon::parse((string) $transaction->date)->toDateString();
                    $amount = TransactionDedupe::amountToDecimalString((string) $transaction->amount);
                    $bucketKey = $date.'|'.$amount.'|'.$logicalDescription;

                    $buckets[$bucketKey] ??= [];
                    $buckets[$bucketKey][] = [
                        'id' => (int) $transaction->id,
                        'account_id' => (int) $transaction->account_id,
                        'user_id' => (int) $transaction->user_id,
                        'import_id' => $transaction->import_id !== null ? (int) $transaction->import_id : null,
                        'transfer_id' => $transaction->transfer_id !== null && $transaction->transfer_id !== ''
                            ? (string) $transaction->transfer_id
                            : null,
                        'date' => $date,
                        'amount' => $amount,
                        'description' => $logicalDescription,
                    ];
                }
            });

        $groups = [];

        foreach ($buckets as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            usort($rows, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

            $keep = $rows[0];
            $duplicateIds = array_values(array_map(
                fn (array $row): int => $row['id'],
                array_slice($rows, 1),
            ));

            $groups[] = [
                'key' => [
                    'date' => $keep['date'],
                    'amount' => $keep['amount'],
                    'description' => $keep['description'],
                ],
                'keep_id' => $keep['id'],
                'duplicate_ids' => $duplicateIds,
                'transactions' => array_map(fn (array $row): array => [
                    'id' => $row['id'],
                    'account_id' => $row['account_id'],
                    'user_id' => $row['user_id'],
                    'import_id' => $row['import_id'],
                    'transfer_id' => $row['transfer_id'],
                ], $rows),
            ];
        }

        usort($groups, fn (array $a, array $b): int => $a['keep_id'] <=> $b['keep_id']);

        return $groups;
    }
}

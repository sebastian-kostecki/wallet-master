<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Exceptions\DomainException;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTransaction
{
    public function __construct(
        private DescriptionMemoryRepository $descriptionMemory,
    ) {}

    /**
     * @param array{
     *   account_id: int,
     *   date: string,
     *   booked_at?: ?string,
     *   amount: numeric-string|float|int,
     *   description: string,
     *   subject?: ?string,
     * } $validated
     */
    public function handle(Transaction $transaction, array $validated): void
    {
        DB::transaction(function () use ($transaction, $validated): void {
            $date = CarbonImmutable::createFromFormat('d-m-Y', $validated['date'])->toDateString();
            $bookedAt = isset($validated['booked_at']) && is_string($validated['booked_at']) && $validated['booked_at'] !== ''
                ? CarbonImmutable::createFromFormat('d-m-Y', $validated['booked_at'])->toDateString()
                : $date;
            $newAmount = TransactionDedupe::amountToDecimalString($validated['amount']);

            try {
                $transactionType = $this->resolveTransactionType($transaction, $newAmount);
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['amount' => $e->getMessage()]);
            }

            $normalizedDescription = TransactionDedupe::normalizeDescription($validated['description']);
            $dedupeHash = TransactionDedupe::dedupeHash($bookedAt, $newAmount, $normalizedDescription);

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
                $transaction->booked_at = $bookedAt;
                $transaction->amount = $newAmount;
                $transaction->type = $transactionType;
                $transaction->description = $validated['description'];
                $transaction->subject = $validated['subject'] ?? null;
                $transaction->normalized_description = $normalizedDescription;
                $transaction->dedupe_hash = $dedupeHash;
                $transaction->save();

                $account->current_balance = bcadd((string) $account->current_balance, $delta, 2);
                $account->save();

                $this->rememberDescriptionMemoryAfterCommit($transaction, $account->bank);

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
            $transaction->booked_at = $bookedAt;
            $transaction->amount = $newAmount;
            $transaction->type = $transactionType;
            $transaction->description = $validated['description'];
            $transaction->subject = $validated['subject'] ?? null;
            $transaction->normalized_description = $normalizedDescription;
            $transaction->dedupe_hash = $dedupeHash;
            $transaction->save();

            $oldAccount->current_balance = bcsub((string) $oldAccount->current_balance, $oldAmount, 2);
            $oldAccount->save();

            $newAccount->current_balance = bcadd((string) $newAccount->current_balance, $newAmount, 2);
            $newAccount->save();

            $this->rememberDescriptionMemoryAfterCommit($transaction, $newAccount->bank);
        });
    }

    private function resolveTransactionType(Transaction $transaction, string $newAmount): TransactionType
    {
        if ($transaction->type === TransactionType::Adjustment || $transaction->type === TransactionType::Transfer) {
            return $transaction->type;
        }

        return TransactionType::fromAmount($newAmount);
    }

    private function rememberDescriptionMemoryAfterCommit(Transaction $transaction, Bank $bank): void
    {
        if ($bank === Bank::Cash) {
            return;
        }

        if ($transaction->import_id === null) {
            return;
        }

        $raw = (string) ($transaction->raw_statement_description ?? '');
        if (trim($raw) === '') {
            return;
        }

        $description = (string) ($transaction->description ?? '');
        if (trim($description) === '') {
            return;
        }

        DB::afterCommit(function () use ($transaction, $bank, $raw, $description): void {
            $this->descriptionMemory->remember(
                userId: (int) $transaction->user_id,
                bank: $bank,
                rawStatementDescription: $raw,
                subject: $transaction->subject,
                description: $description,
            );
        });
    }
}

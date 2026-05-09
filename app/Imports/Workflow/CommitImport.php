<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Imports\BankAdapters\BankImportAdapter;
use App\Models\Account;
use App\Models\Import;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CommitImport
{
    public function __construct(
        private ResolveImportSourceFile $resolveImportSourceFile,
        private EnrichImportRowDescription $enrichImportRowDescription,
    ) {}

    /**
     * Process the import when it is safe to do so. Returns false if the import
     * was skipped (already committed or unexpected status).
     */
    public function handle(Import $import): bool
    {
        $import->loadMissing(['account', 'user']);

        $deleteRelativePath = DB::transaction(function () use ($import): ?string {
            $lockedImport = Import::query()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedImport->committed_at !== null) {
                return null;
            }

            if (! in_array($lockedImport->status, [ImportStatus::Queued->value, ImportStatus::Processing->value], true)) {
                return null;
            }

            $paths = $this->resolveImportSourceFile->resolve($lockedImport);
            $account = $lockedImport->account()->lockForUpdate()->firstOrFail();
            $adapter = $this->resolveAdapter($lockedImport, $account);

            /** @var array{date: string, amount: string, description: string, subject?: ?string}|array{}|null $mapping */
            $mapping = $lockedImport->mapping;

            if ($mapping === null || $mapping === []) {
                throw new RuntimeException('Import is missing a column mapping.');
            }

            $counters = new ImportCommitCounters;

            foreach ($adapter->readRows($paths['absolute']) as $row) {
                $counters->rowIndex++;
                $counters->rowsTotal++;

                try {
                    $parsedRow = $adapter->normalizeRow($row, $mapping);
                } catch (\Throwable) {
                    $counters->rowsFailedValidation++;
                    Log::debug('Import row validation failed.', [
                        'import_id' => $lockedImport->id,
                        'row_index' => $counters->rowIndex,
                    ]);

                    continue;
                }

                $normalizedDescription = TransactionDedupe::normalizeDescription($parsedRow->description);
                $dedupeHash = TransactionDedupe::dedupeHash($parsedRow->date, $parsedRow->amount, $normalizedDescription);

                $exists = Transaction::query()
                    ->where('account_id', $account->id)
                    ->where('dedupe_hash', $dedupeHash)
                    ->exists();

                if ($exists) {
                    $counters->rowsSkippedDuplicate++;

                    continue;
                }

                $enriched = $this->enrichImportRowDescription->enrich(
                    import: $lockedImport,
                    bank: $adapter->bank(),
                    rawStatementDescription: $parsedRow->rawStatementDescription,
                    description: $parsedRow->rawStatementDescription,
                    subject: $parsedRow->subject,
                );

                $transactionType = bccomp($parsedRow->amount, '0', 2) === -1 ? 'expense' : 'income';

                Transaction::query()->create([
                    'user_id' => $lockedImport->user_id,
                    'account_id' => $account->id,
                    'currency_id' => $account->currency_id,
                    'import_id' => $lockedImport->id,
                    'date' => $parsedRow->date,
                    'amount' => $parsedRow->amount,
                    'type' => $transactionType,
                    'description' => $enriched['description'],
                    'subject' => $enriched['subject'],
                    'raw_statement_description' => $parsedRow->rawStatementDescription,
                    'normalized_description' => $normalizedDescription,
                    'dedupe_hash' => $dedupeHash,
                ]);

                $counters->rowsImported++;
                $counters->importedAmountSum = bcadd($counters->importedAmountSum, $parsedRow->amount, 2);
            }

            $account->current_balance = bcadd($account->current_balance, $counters->importedAmountSum, 2);
            $account->save();

            $lockedImport->rows_total = $counters->rowsTotal;
            $lockedImport->rows_imported = $counters->rowsImported;
            $lockedImport->rows_skipped_duplicate = $counters->rowsSkippedDuplicate;
            $lockedImport->rows_failed_validation = $counters->rowsFailedValidation;
            $lockedImport->status = ImportStatus::Committed->value;
            $lockedImport->committed_at = now();
            $lockedImport->save();

            return $paths['relative'];
        });

        if ($deleteRelativePath === null) {
            return false;
        }

        $import->refresh();

        Storage::disk('local')->delete($deleteRelativePath);

        return true;
    }

    private function resolveAdapter(Import $lockedImport, Account $account): BankImportAdapter
    {
        $bankValue = data_get($lockedImport->details, 'bank');

        if (is_string($bankValue) && $bankValue !== '') {
            $bank = Bank::tryFrom($bankValue);

            if ($bank !== null && $bank->supportsImport()) {
                return $bank->makeImportAdapter();
            }
        }

        $parser = data_get($lockedImport->details, 'parser');

        if (is_string($parser) && $parser !== '' && class_exists($parser) && is_subclass_of($parser, BankImportAdapter::class)) {
            return new $parser;
        }

        $bank = $account->bank;

        if ($bank === null || ! $bank->supportsImport()) {
            throw new RuntimeException('This account bank does not support imports.');
        }

        return $bank->makeImportAdapter();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Imports\BankImportAdapterResolver;
use App\Models\Import;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class CommitImport
{
    public function __construct(
        public BankImportAdapterResolver $resolver,
        public ResolveImportSourceFile $resolveImportSourceFile,
        public EnrichImportRowDescription $enrichImportRowDescription,
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

            if (! in_array($lockedImport->status, ['queued', 'processing'], true)) {
                return null;
            }

            $paths = $this->resolveImportSourceFile->resolve($lockedImport);
            $adapter = $this->resolver->resolve($lockedImport->account->bank);

            /** @var array{date: string, amount: string, description: string, subject?: ?string}|array{}|null $mapping */
            $mapping = $lockedImport->mapping;

            if ($mapping === null || $mapping === []) {
                throw new \RuntimeException('Import is missing a column mapping.');
            }

            $account = $lockedImport->account()->lockForUpdate()->firstOrFail();

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
                    bank: $account->bank,
                    rawStatementDescription: $parsedRow->rawStatementDescription,
                    description: $parsedRow->rawStatementDescription,
                    subject: $parsedRow->subject,
                );

                $description = $enriched['description'];
                $subject = $enriched['subject'];

                $transactionType = bccomp($parsedRow->amount, '0', 2) === -1 ? 'expense' : 'income';

                Transaction::query()->create([
                    'user_id' => $lockedImport->user_id,
                    'account_id' => $account->id,
                    'currency_id' => $account->currency_id,
                    'import_id' => $lockedImport->id,
                    'date' => $parsedRow->date,
                    'amount' => $parsedRow->amount,
                    'type' => $transactionType,
                    'description' => $description,
                    'subject' => $subject,
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
            $lockedImport->status = 'committed';
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
}

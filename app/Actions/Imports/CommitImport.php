<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Events\Imports\ImportEnrichmentTypesenseHit;
use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use App\Imports\BankImportAdapterResolver;
use App\Models\Import;
use App\Models\Transaction;
use App\Support\DescriptionMemory\DescriptionMemoryRepository;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CommitImport
{
    public function __construct(
        public BankImportAdapterResolver $resolver,
        public DescriptionMemoryRepository $descriptionMemory,
    ) {}

    public function handle(Import $import): void
    {
        $import->loadMissing(['account', 'user']);

        $sourceFile = (string) data_get($import->details, 'source_file', '');
        if ($sourceFile === '' || ! Storage::disk('local')->exists($sourceFile)) {
            throw new \RuntimeException('Import source file does not exist.');
        }

        /** @var array{date:string, amount:string, description:string, subject?:?string} $mapping */
        $mapping = $import->mapping ?? [];
        $adapter = $this->resolver->resolve($import->account->bank);
        $absolutePath = Storage::disk('local')->path($sourceFile);

        $rowsTotal = 0;
        $rowsImported = 0;
        $rowsSkippedDuplicate = 0;
        $rowsFailedValidation = 0;
        $importedAmountSum = '0.00';

        DB::transaction(function () use (
            $import,
            $adapter,
            $absolutePath,
            $mapping,
            &$rowsTotal,
            &$rowsImported,
            &$rowsSkippedDuplicate,
            &$rowsFailedValidation,
            &$importedAmountSum,
        ): void {
            $account = $import->account()->lockForUpdate()->firstOrFail();

            foreach ($adapter->readRows($absolutePath) as $row) {
                $rowsTotal++;

                try {
                    $parsedRow = $adapter->normalizeRow($row, $mapping);
                } catch (\Throwable) {
                    $rowsFailedValidation++;

                    continue;
                }

                $normalizedDescription = TransactionDedupe::normalizeDescription($parsedRow->description);
                $dedupeHash = TransactionDedupe::dedupeHash($parsedRow->date, $parsedRow->amount, $normalizedDescription);

                $exists = Transaction::query()
                    ->where('account_id', $account->id)
                    ->where('dedupe_hash', $dedupeHash)
                    ->exists();

                if ($exists) {
                    $rowsSkippedDuplicate++;

                    continue;
                }

                $description = $parsedRow->rawStatementDescription;
                $subject = $parsedRow->subject;

                if ($description !== '' && $account->bank->supportsImport()) {
                    $suggested = $this->descriptionMemory->suggest(
                        userId: (int) $import->user_id,
                        bank: $account->bank,
                        rawStatementDescription: $description,
                    );

                    if ($suggested !== null) {
                        if ($suggested->subject !== null && trim($suggested->subject) !== '') {
                            $subject = $suggested->subject;
                        }

                        if ($suggested->description !== null && trim($suggested->description) !== '') {
                            $description = $suggested->description;
                        }

                        event(new ImportEnrichmentTypesenseHit(
                            userId: (int) $import->user_id,
                            importId: (int) $import->id,
                            bank: $account->bank,
                            matchType: $suggested->matchType,
                            score: $suggested->score,
                        ));
                    } else {
                        event(new ImportEnrichmentTypesenseMiss(
                            userId: (int) $import->user_id,
                            importId: (int) $import->id,
                            bank: $account->bank,
                        ));
                    }
                }

                Transaction::query()->create([
                    'user_id' => $import->user_id,
                    'account_id' => $account->id,
                    'currency_id' => $account->currency_id,
                    'import_id' => $import->id,
                    'date' => $parsedRow->date,
                    'amount' => $parsedRow->amount,
                    'type' => ((float) $parsedRow->amount) < 0 ? 'expense' : 'income',
                    'description' => $description,
                    'subject' => $subject,
                    'raw_statement_description' => $parsedRow->rawStatementDescription,
                    'normalized_description' => $normalizedDescription,
                    'dedupe_hash' => $dedupeHash,
                ]);

                $rowsImported++;
                $importedAmountSum = bcadd($importedAmountSum, $parsedRow->amount, 2);
            }

            $account->current_balance = bcadd((string) $account->current_balance, $importedAmountSum, 2);
            $account->save();
        });

        $import->rows_total = $rowsTotal;
        $import->rows_imported = $rowsImported;
        $import->rows_skipped_duplicate = $rowsSkippedDuplicate;
        $import->rows_failed_validation = $rowsFailedValidation;
        $import->status = 'committed';
        $import->committed_at = now();
        $import->save();

        Storage::disk('local')->delete($sourceFile);
    }
}

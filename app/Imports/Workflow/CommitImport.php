<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Enums\TransactionType;
use App\Events\ImportStatusUpdated;
use App\Exceptions\DomainException;
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
    private ?float $lastProgressBroadcastAt = null;

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

        $setup = DB::transaction(function () use ($import): ?array {
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
            $account = $lockedImport->account()->firstOrFail();
            $adapter = $this->resolveAdapter($lockedImport, $account);

            /** @var array{date: string, amount: string, description: string, subject?: ?string}|array{}|null $mapping */
            $mapping = $lockedImport->mapping;

            if ($mapping === null || $mapping === []) {
                throw new RuntimeException('Import is missing a column mapping.');
            }

            return [
                'lockedImport' => $lockedImport,
                'paths' => $paths,
                'account' => $account,
                'adapter' => $adapter,
                'mapping' => $mapping,
            ];
        });

        if ($setup === null) {
            return false;
        }

        /** @var Import $lockedImport */
        $lockedImport = $setup['lockedImport'];
        /** @var array{absolute: string, relative: string} $paths */
        $paths = $setup['paths'];
        /** @var Account $account */
        $account = $setup['account'];
        /** @var BankImportAdapter $adapter */
        $adapter = $setup['adapter'];
        /** @var array{date: string, amount: string, description: string, subject?: ?string} $mapping */
        $mapping = $setup['mapping'];

        $counters = new ImportCommitCounters;
        /** @var array<string, true> $seenDedupeHashes */
        $seenDedupeHashes = [];
        /** @var list<array<string, mixed>> $pendingInserts */
        $pendingInserts = [];
        $chunkSize = max(1, (int) config('imports.chunk_size', 500));
        $timestamp = now()->toDateTimeString();

        foreach ($adapter->readRows($paths['absolute']) as $row) {
            $counters->rowIndex++;
            $counters->rowsTotal++;

            $insertRow = $this->buildInsertRow(
                lockedImport: $lockedImport,
                account: $account,
                adapter: $adapter,
                mapping: $mapping,
                row: $row,
                counters: $counters,
                seenDedupeHashes: $seenDedupeHashes,
                timestamp: $timestamp,
            );

            if ($insertRow === null) {
                continue;
            }

            $pendingInserts[] = $insertRow;

            if (count($pendingInserts) >= $chunkSize) {
                $this->flushChunk($lockedImport, $pendingInserts, $counters);
                $pendingInserts = [];
            }
        }

        if ($pendingInserts !== []) {
            $this->flushChunk($lockedImport, $pendingInserts, $counters);
        }

        $deleteRelativePath = DB::transaction(function () use ($lockedImport, $account, $counters, $paths): string {
            $lockedAccount = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedImportFresh = Import::query()
                ->whereKey($lockedImport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAccount->current_balance = bcadd($lockedAccount->current_balance, $counters->importedAmountSum, 2);
            $lockedAccount->save();

            $lockedImportFresh->rows_total = $counters->rowsTotal;
            $lockedImportFresh->rows_imported = $counters->rowsImported;
            $lockedImportFresh->rows_skipped_duplicate = $counters->rowsSkippedDuplicate;
            $lockedImportFresh->rows_failed_validation = $counters->rowsFailedValidation;
            $lockedImportFresh->status = ImportStatus::Committed->value;
            $lockedImportFresh->committed_at = now();
            $lockedImportFresh->save();

            return $paths['relative'];
        });

        $import->refresh();

        Storage::disk('local')->delete($deleteRelativePath);

        event(new ImportStatusUpdated($import));

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: ?string}  $mapping
     * @param  array<string, true>  $seenDedupeHashes
     * @return array<string, mixed>|null
     */
    private function buildInsertRow(
        Import $lockedImport,
        Account $account,
        BankImportAdapter $adapter,
        array $mapping,
        array $row,
        ImportCommitCounters $counters,
        array &$seenDedupeHashes,
        string $timestamp,
    ): ?array {
        try {
            $parsedRow = $adapter->normalizeRow($row, $mapping);
        } catch (\Throwable) {
            $counters->rowsFailedValidation++;
            Log::debug('Import row validation failed.', [
                'import_id' => $lockedImport->id,
                'row_index' => $counters->rowIndex,
            ]);

            return null;
        }

        $normalizedDescription = TransactionDedupe::normalizeDescription($parsedRow->description);
        $dedupeHash = TransactionDedupe::dedupeHash($parsedRow->date, $parsedRow->amount, $normalizedDescription);
        $dedupeKey = bin2hex($dedupeHash);

        if (isset($seenDedupeHashes[$dedupeKey])) {
            $counters->rowsSkippedDuplicate++;

            return null;
        }

        $exists = Transaction::query()
            ->where('account_id', $account->id)
            ->where('dedupe_hash', $dedupeHash)
            ->exists();

        if ($exists) {
            $counters->rowsSkippedDuplicate++;

            return null;
        }

        $seenDedupeHashes[$dedupeKey] = true;

        $enriched = $this->enrichImportRowDescription->enrich(
            import: $lockedImport,
            bank: $adapter->bank(),
            rawStatementDescription: $parsedRow->rawStatementDescription,
            description: $parsedRow->rawStatementDescription,
            subject: $parsedRow->subject,
        );

        try {
            $transactionType = TransactionType::fromAmount($parsedRow->amount);
        } catch (DomainException) {
            $counters->rowsFailedValidation++;

            return null;
        }

        $counters->rowsImported++;
        $counters->importedAmountSum = bcadd($counters->importedAmountSum, $parsedRow->amount, 2);

        return [
            'user_id' => $lockedImport->user_id,
            'account_id' => $account->id,
            'currency_id' => $account->currency_id,
            'import_id' => $lockedImport->id,
            'date' => $parsedRow->date,
            'booked_at' => $parsedRow->date,
            'amount' => $parsedRow->amount,
            'type' => $transactionType->value,
            'description' => $enriched['description'],
            'subject' => $enriched['subject'],
            'raw_statement_description' => $parsedRow->rawStatementDescription,
            'normalized_description' => $normalizedDescription,
            'dedupe_hash' => $dedupeHash,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flushChunk(Import $import, array $rows, ImportCommitCounters $counters): void
    {
        DB::transaction(function () use ($import, $rows, $counters): void {
            if ($rows !== []) {
                Transaction::query()->insert($rows);
            }

            Import::query()
                ->whereKey($import->id)
                ->update([
                    'rows_total' => $counters->rowsTotal,
                    'rows_imported' => $counters->rowsImported,
                    'rows_skipped_duplicate' => $counters->rowsSkippedDuplicate,
                    'rows_failed_validation' => $counters->rowsFailedValidation,
                    'updated_at' => now(),
                ]);
        });

        $this->broadcastProgressIfDue($import->fresh() ?? $import);
    }

    private function broadcastProgressIfDue(Import $import): void
    {
        $interval = max(1, (int) config('imports.progress_broadcast_interval_seconds', 1));
        $now = microtime(true);

        if ($this->lastProgressBroadcastAt !== null && ($now - $this->lastProgressBroadcastAt) < $interval) {
            return;
        }

        $this->lastProgressBroadcastAt = $now;
        event(new ImportStatusUpdated($import));
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

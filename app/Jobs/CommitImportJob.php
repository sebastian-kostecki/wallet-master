<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Imports\CommitImport;
use App\Events\ImportStatusUpdated;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;

#[UniqueFor(3600)]
final class CommitImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $importId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(CommitImport $commitImport): void
    {
        $import = Import::query()->find($this->importId);
        if ($import === null || $import->committed_at !== null) {
            return;
        }

        $import->status = 'processing';
        $import->save();

        $processed = false;

        try {
            $processed = $commitImport->handle($import);
        } catch (\Throwable $exception) {
            if ($this->attempts() >= $this->tries) {
                $import->status = 'failed';
                $import->error_summary = 'Import failed due to a system error.';
                $import->save();

                event(new ImportStatusUpdated($import));
            } else {
                $import->status = 'queued';
                $import->save();
            }

            throw $exception;
        }

        $import->refresh();

        if ($processed) {
            event(new ImportStatusUpdated($import));
        }
    }
}

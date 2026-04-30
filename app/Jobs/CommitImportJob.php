<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Imports\CommitImport;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CommitImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $importId,
    ) {}

    public function handle(CommitImport $commitImport): void
    {
        $import = Import::query()->find($this->importId);
        if ($import === null || $import->committed_at !== null) {
            return;
        }

        $import->status = 'processing';
        $import->save();

        try {
            $commitImport->handle($import);
        } catch (\Throwable $exception) {
            $import->status = 'failed';
            $import->error_summary = 'Import failed due to a system error.';
            $import->save();

            throw $exception;
        }
    }
}

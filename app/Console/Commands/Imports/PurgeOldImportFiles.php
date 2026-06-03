<?php

declare(strict_types=1);

namespace App\Console\Commands\Imports;

use App\Enums\ImportStatus;
use App\Models\Import;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('imports:purge-old-files {--days=30 : Delete failed import source files older than this many days} {--dry-run : List files without deleting them}')]
#[Description('Delete source files for failed imports older than the retention period.')]
final class PurgeOldImportFiles extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $deletedFiles = 0;
        $skippedImports = 0;

        Import::query()
            ->where('status', ImportStatus::Failed->value)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($imports) use ($dryRun, &$deletedFiles, &$skippedImports): void {
                foreach ($imports as $import) {
                    $relativePath = (string) data_get($import->details, 'source_file', '');

                    if ($relativePath === '' || ! Storage::disk('local')->exists($relativePath)) {
                        $skippedImports++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[dry-run] Would delete {$relativePath} (import #{$import->id})");
                    } else {
                        Storage::disk('local')->delete($relativePath);
                        $this->line("Deleted {$relativePath} (import #{$import->id})");
                    }

                    $deletedFiles++;
                }
            });

        $this->info(
            ($dryRun ? '[dry-run] ' : '')
            ."Processed failed imports before {$cutoff->toDateString()}: deleted {$deletedFiles} file(s), skipped {$skippedImports} import(s) without a stored file."
        );

        return self::SUCCESS;
    }
}

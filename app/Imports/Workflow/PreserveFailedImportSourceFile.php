<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Models\Import;
use Illuminate\Support\Facades\Storage;

final class PreserveFailedImportSourceFile
{
    public function execute(Import $import): void
    {
        $relative = (string) data_get($import->details, 'source_file', '');

        if ($relative === '' || ! Storage::disk('local')->exists($relative)) {
            return;
        }

        $extension = pathinfo($relative, PATHINFO_EXTENSION) ?: 'csv';
        $failedRelative = dirname($relative).'/source-failed.'.$extension;

        if (Storage::disk('local')->exists($failedRelative)) {
            Storage::disk('local')->delete($failedRelative);
        }

        Storage::disk('local')->move($relative, $failedRelative);

        $details = $import->details ?? [];
        $details['source_file'] = $failedRelative;
        $details['source_file_failed_at'] = now()->toIso8601String();

        $import->details = $details;
        $import->save();
    }
}

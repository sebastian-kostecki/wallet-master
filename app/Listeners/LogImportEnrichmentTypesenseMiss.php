<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use Illuminate\Support\Facades\Log;

final class LogImportEnrichmentTypesenseMiss
{
    public function handle(ImportEnrichmentTypesenseMiss $event): void
    {
        Log::info('import_enrichment_typesense_miss', [
            'user_id' => $event->userId,
            'import_id' => $event->importId,
            'bank' => $event->bank->value,
        ]);
    }
}

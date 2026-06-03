<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use App\Telemetry\Event;

final class LogImportEnrichmentTypesenseMiss
{
    public function handle(ImportEnrichmentTypesenseMiss $event): void
    {
        Event::record('import_enrichment_typesense_miss', [
            'import_id' => $event->importId,
            'bank' => $event->bank->value,
        ], $event->userId);
    }
}

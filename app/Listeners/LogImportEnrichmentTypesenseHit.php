<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Imports\ImportEnrichmentTypesenseHit;
use App\Telemetry\Event;

final class LogImportEnrichmentTypesenseHit
{
    public function handle(ImportEnrichmentTypesenseHit $event): void
    {
        Event::record('import_enrichment_typesense_hit', [
            'import_id' => $event->importId,
            'bank' => $event->bank->value,
            'match_type' => $event->matchType,
            'score' => $event->score,
        ], $event->userId);
    }
}

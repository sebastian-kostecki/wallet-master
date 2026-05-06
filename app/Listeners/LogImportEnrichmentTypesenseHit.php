<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Imports\ImportEnrichmentTypesenseHit;
use Illuminate\Support\Facades\Log;

final class LogImportEnrichmentTypesenseHit
{
    public function handle(ImportEnrichmentTypesenseHit $event): void
    {
        Log::info('import_enrichment_typesense_hit', [
            'user_id' => $event->userId,
            'import_id' => $event->importId,
            'bank' => $event->bank->value,
            'match_type' => $event->matchType,
            'score' => $event->score,
        ]);
    }
}

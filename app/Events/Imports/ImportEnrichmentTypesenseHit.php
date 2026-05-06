<?php

declare(strict_types=1);

namespace App\Events\Imports;

use App\Enums\Bank;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ImportEnrichmentTypesenseHit implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $userId,
        public int $importId,
        public Bank $bank,
        public string $matchType,
        public int $score,
    ) {}
}

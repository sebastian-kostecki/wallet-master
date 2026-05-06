<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\Bank;
use App\Events\Imports\ImportEnrichmentTypesenseHit;
use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use App\Models\Import;
use App\Support\DescriptionMemory\DescriptionMemoryRepository;

final readonly class EnrichImportRowDescription
{
    public function __construct(
        private DescriptionMemoryRepository $descriptionMemory,
    ) {}

    /**
     * @return array{description: string, subject: ?string}
     */
    public function enrich(
        Import $import,
        Bank $bank,
        string $rawStatementDescription,
        string $description,
        ?string $subject,
    ): array {
        if ($rawStatementDescription === '' || ! $bank->supportsImport()) {
            return ['description' => $description, 'subject' => $subject];
        }

        $suggested = $this->descriptionMemory->suggest(
            userId: (int) $import->user_id,
            bank: $bank,
            rawStatementDescription: $rawStatementDescription,
        );

        if ($suggested !== null) {
            if ($suggested->subject !== null && trim($suggested->subject) !== '') {
                $subject = $suggested->subject;
            }

            if ($suggested->description !== null && trim($suggested->description) !== '') {
                $description = $suggested->description;
            }

            event(new ImportEnrichmentTypesenseHit(
                userId: (int) $import->user_id,
                importId: (int) $import->id,
                bank: $bank,
                matchType: $suggested->matchType,
                score: $suggested->score,
            ));
        } else {
            event(new ImportEnrichmentTypesenseMiss(
                userId: (int) $import->user_id,
                importId: (int) $import->id,
                bank: $bank,
            ));
        }

        return ['description' => $description, 'subject' => $subject];
    }
}

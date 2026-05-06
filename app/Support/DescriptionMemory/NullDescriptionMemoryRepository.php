<?php

declare(strict_types=1);

namespace App\Support\DescriptionMemory;

use App\Enums\Bank;

final class NullDescriptionMemoryRepository implements DescriptionMemoryRepository
{
    public function remember(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
        ?string $subject,
        string $description,
    ): void {
        // no-op (best-effort degradation)
    }

    public function suggest(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
    ): ?SuggestedFields {
        return null;
    }
}

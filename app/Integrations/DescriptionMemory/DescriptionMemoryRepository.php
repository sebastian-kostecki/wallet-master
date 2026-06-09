<?php

declare(strict_types=1);

namespace App\Integrations\DescriptionMemory;

use App\Enums\Bank;

interface DescriptionMemoryRepository
{
    public function remember(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
        ?string $subject,
        string $description,
        ?int $categoryId = null,
    ): void;

    public function suggest(
        int $userId,
        Bank $bank,
        string $rawStatementDescription,
    ): ?SuggestedFields;
}

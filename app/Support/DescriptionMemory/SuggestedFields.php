<?php

declare(strict_types=1);

namespace App\Support\DescriptionMemory;

final readonly class SuggestedFields
{
    public function __construct(
        public ?string $subject,
        public ?string $description,
        public string $matchType,
        public int $score,
    ) {}
}

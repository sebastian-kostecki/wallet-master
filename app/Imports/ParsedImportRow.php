<?php

declare(strict_types=1);

namespace App\Imports;

/**
 * @param  numeric-string  $amount
 *
 * @property-read numeric-string $amount
 */
final readonly class ParsedImportRow
{
    public function __construct(
        public string $date,
        public string $amount,
        public string $description,
        public ?string $subject,
        public string $rawStatementDescription,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Models\Import;

final readonly class PrepareImportUploadResult
{
    /**
     * @param  list<string>|null  $headers
     */
    private function __construct(
        public bool $success,
        public ?Import $import,
        public ?array $headers,
        public ?string $code,
        public ?string $message,
    ) {}

    /**
     * @param  list<string>  $headers
     */
    public static function ok(Import $import, array $headers): self
    {
        return new self(true, $import, $headers, null, null);
    }

    public static function unreadableContents(): self
    {
        return new self(false, null, null, 'unreadable_file', 'Unable to read import file contents.');
    }

    public static function unreadableHeaders(): self
    {
        return new self(false, null, null, 'unreadable_file', 'Unable to read import file headers.');
    }

    public static function unrecognizedHeaders(): self
    {
        return new self(false, null, null, 'unrecognized_headers', 'Could not recognize import file columns.');
    }

    public static function bankDoesNotSupportImport(): self
    {
        return new self(false, null, null, 'bank_unsupported', 'This account bank does not support file imports.');
    }
}

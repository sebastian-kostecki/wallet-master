<?php

declare(strict_types=1);

namespace App\Imports\BankAdapters;

use App\Enums\Bank;
use App\Imports\ParsedImportRow;

interface BankImportAdapter
{
    public function bank(): Bank;

    /**
     * @return list<string>
     */
    public function extractHeaders(string $path): array;

    /**
     * @return iterable<int, array<string, string>>
     */
    public function readRows(string $path): iterable;

    /**
     * @param  array<string, string>  $row
     * @param  array{date:string, amount:string, description:string, subject?:?string}  $mapping
     */
    public function normalizeRow(array $row, array $mapping): ParsedImportRow;

    /**
     * Resolve a default column mapping based on the file headers.
     *
     * @param  list<string>  $headers
     * @return array{date:string, amount:string, description:string, subject?:?string}|null
     */
    public function defaultMapping(array $headers): ?array;
}

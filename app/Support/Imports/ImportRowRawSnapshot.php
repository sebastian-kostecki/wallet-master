<?php

declare(strict_types=1);

namespace App\Support\Imports;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ImportRowRawSnapshot
{
    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string}  $mapping
     * @return array{
     *   date_raw: string,
     *   amount_raw: string,
     *   description_raw: string,
     *   subject_raw: ?string,
     * }
     */
    public static function fromMappedRow(array $row, array $mapping): array
    {
        $dateRaw = trim((string) Arr::get($row, $mapping['date'], ''));
        $amountRaw = trim((string) Arr::get($row, $mapping['amount'], ''));
        $descriptionRaw = Str::limit(trim((string) Arr::get($row, $mapping['description'], '')), 2000, '');
        $subjectRaw = isset($mapping['subject'])
            ? Str::limit(trim((string) Arr::get($row, $mapping['subject'], '')), 255, '')
            : '';

        return [
            'date_raw' => $dateRaw,
            'amount_raw' => $amountRaw,
            'description_raw' => $descriptionRaw,
            'subject_raw' => $subjectRaw !== '' ? $subjectRaw : null,
        ];
    }
}

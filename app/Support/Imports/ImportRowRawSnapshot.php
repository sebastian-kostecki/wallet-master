<?php

declare(strict_types=1);

namespace App\Support\Imports;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ImportRowRawSnapshot
{
    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
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
        $subjectRaw = self::resolveSubjectRaw($row, $mapping);

        return [
            'date_raw' => $dateRaw,
            'amount_raw' => $amountRaw,
            'description_raw' => $descriptionRaw,
            'subject_raw' => $subjectRaw,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    private static function resolveSubjectRaw(array $row, array $mapping): ?string
    {
        if (isset($mapping['subject'])) {
            $raw = Str::limit(trim((string) Arr::get($row, $mapping['subject'], '')), 255, '');

            return $raw !== '' ? $raw : null;
        }

        $amountRaw = trim((string) Arr::get($row, $mapping['amount'], ''));

        if ($amountRaw === '') {
            return null;
        }

        $parsedAmount = AmountParser::parse($amountRaw);
        $columnKey = SignBasedSubjectColumnResolver::resolveColumnKey($mapping, $parsedAmount);

        if ($columnKey === null) {
            return null;
        }

        $raw = Str::limit(trim((string) Arr::get($row, $columnKey, '')), 255, '');

        return $raw !== '' ? $raw : null;
    }
}

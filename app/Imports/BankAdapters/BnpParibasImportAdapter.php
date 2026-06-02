<?php

declare(strict_types=1);

namespace App\Imports\BankAdapters;

use App\Enums\Bank;
use App\Imports\ParsedImportRow;
use App\Support\Imports\AmountParser;
use App\Support\Imports\DateParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

final class BnpParibasImportAdapter extends AbstractBankImportAdapter
{
    public function bank(): Bank
    {
        return Bank::BnpParibas;
    }

    public function defaultMapping(array $headers): ?array
    {
        $fallback = parent::defaultMapping($headers);
        if ($fallback !== null) {
            return $fallback;
        }

        $date = $this->findHeader($headers, 'Data transakcji');
        $amount = $this->findHeader($headers, 'Kwota');
        $description = $this->findHeader($headers, 'Opis');
        if ($description === null) {
            $description = $this->findHeader($headers, 'Typ transakcji');
        }

        if ($date === null || $amount === null || $description === null) {
            return null;
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
        ];
    }

    public function normalizeRow(array $row, array $mapping): ParsedImportRow
    {
        $dateRaw = trim((string) Arr::get($row, $mapping['date'], ''));
        $amountRaw = trim((string) Arr::get($row, $mapping['amount'], ''));
        $descriptionRaw = $this->resolveDescription($row, $mapping);
        $subjectRaw = isset($mapping['subject']) ? trim((string) Arr::get($row, $mapping['subject'], '')) : '';

        if ($dateRaw === '' || $amountRaw === '' || $descriptionRaw === '') {
            throw new RuntimeException('Required import columns are empty.');
        }

        $date = DateParser::parse($dateRaw);
        $amount = AmountParser::parse($amountRaw);

        return new ParsedImportRow(
            date: $date,
            amount: $amount,
            description: Str::limit($descriptionRaw, 2000, ''),
            subject: $subjectRaw !== '' ? Str::limit($subjectRaw, 255, '') : null,
            rawStatementDescription: Str::limit($descriptionRaw, 2000, ''),
        );
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: ?string}  $mapping
     */
    private function resolveDescription(array $row, array $mapping): string
    {
        $primary = trim((string) Arr::get($row, $mapping['description'], ''));

        if ($primary !== '') {
            return $primary;
        }

        $typHeader = $this->findHeader(array_keys($row), 'Typ transakcji');

        if ($typHeader === null) {
            return '';
        }

        return trim((string) Arr::get($row, $typHeader, ''));
    }
}

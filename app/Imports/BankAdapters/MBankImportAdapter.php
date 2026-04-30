<?php

declare(strict_types=1);

namespace App\Imports\BankAdapters;

use App\Enums\Bank;
use App\Imports\ParsedImportRow;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

final class MBankImportAdapter extends AbstractBankImportAdapter
{
    public function bank(): Bank
    {
        return Bank::MBank;
    }

    public function extractHeaders(string $path): array
    {
        $parsed = $this->parseMbankCsv($path);

        return $parsed['headers'];
    }

    public function readRows(string $path): iterable
    {
        $parsed = $this->parseMbankCsv($path);

        foreach ($parsed['rows'] as $row) {
            yield $row;
        }
    }

    public function normalizeRow(array $row, array $mapping): ParsedImportRow
    {
        $dateRaw = trim((string) Arr::get($row, $mapping['date'], ''));
        $amountRaw = trim((string) Arr::get($row, $mapping['amount'], ''));
        $descriptionRaw = trim((string) Arr::get($row, $mapping['description'], ''));
        $subjectRaw = isset($mapping['subject']) ? trim((string) Arr::get($row, $mapping['subject'], '')) : '';

        if ($dateRaw === '' || $amountRaw === '' || $descriptionRaw === '') {
            throw new RuntimeException('Required import columns are empty.');
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $dateRaw)->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException('Invalid transaction date.');
        }

        $amountNormalized = str_replace(['PLN', ' ', ','], ['', '', '.'], strtoupper($amountRaw));
        if (! is_numeric($amountNormalized)) {
            throw new RuntimeException('Invalid transaction amount.');
        }

        $amount = TransactionDedupe::amountToDecimalString($amountNormalized);

        return new ParsedImportRow(
            date: $date,
            amount: $amount,
            description: Str::limit($descriptionRaw, 2000, ''),
            subject: $subjectRaw !== '' ? Str::limit($subjectRaw, 255, '') : null,
            rawStatementDescription: Str::limit($descriptionRaw, 2000, ''),
        );
    }

    /**
     * @return array{headers:list<string>, rows:list<array<string, string>>}
     */
    private function parseMbankCsv(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $headers = [];
        $rows = [];

        foreach ($lines as $lineRaw) {
            $line = str_getcsv((string) $lineRaw, ';');
            $line = array_map(fn ($value): string => trim((string) $value), $line);
            $first = (string) ($line[0] ?? '');

            if ($headers === [] && str_contains($first, '#Data operacji')) {
                $headers = collect($line)
                    ->map(fn (string $value): string => trim((string) Str::of($value)->ltrim('#')))
                    ->filter(fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();

                continue;
            }

            if ($headers === []) {
                continue;
            }

            if ($line === [] || ($line[0] ?? '') === '') {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($line[$index] ?? ''));
            }
            $rows[] = $row;
        }

        if ($headers === []) {
            throw new RuntimeException('Import file has no transaction header section.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}

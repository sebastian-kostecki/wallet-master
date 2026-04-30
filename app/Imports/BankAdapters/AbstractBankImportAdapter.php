<?php

declare(strict_types=1);

namespace App\Imports\BankAdapters;

use App\Imports\ParsedImportRow;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

abstract class AbstractBankImportAdapter implements BankImportAdapter
{
    public function extractHeaders(string $path): array
    {
        $rows = $this->readAllRows($path);

        return $rows['headers'];
    }

    public function readRows(string $path): iterable
    {
        $rows = $this->readAllRows($path);

        foreach ($rows['rows'] as $row) {
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

        $date = $this->parseDate($dateRaw);
        $amount = $this->parseAmount($amountRaw);

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
    private function readAllRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException('Unsupported import file extension.'),
        };
    }

    /**
     * @return array{headers:list<string>, rows:list<array<string, string>>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $delimiter = $this->detectCsvDelimiter($path);
        $headers = [];
        $rows = [];

        while (($row = fgetcsv($handle, separator: $delimiter)) !== false) {
            if ($headers === []) {
                $headers = $this->normalizeHeaders($row);

                continue;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rows[] = $this->toAssociativeRow($headers, $row);
        }

        fclose($handle);

        if ($headers === []) {
            throw new RuntimeException('Import file has no headers.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers:list<string>, rows:list<array<string, string>>}
     */
    private function readXlsx(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }

        $sharedStringsRaw = $zip->getFromName('xl/sharedStrings.xml');
        $worksheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! is_string($worksheetRaw)) {
            throw new RuntimeException('Unable to read first worksheet from XLSX.');
        }

        $sharedStrings = [];
        if (is_string($sharedStringsRaw)) {
            $sharedStringsXml = simplexml_load_string($sharedStringsRaw);
            if ($sharedStringsXml !== false && isset($sharedStringsXml->si)) {
                foreach ($sharedStringsXml->si as $item) {
                    $sharedStrings[] = trim((string) ($item->t ?? ''));
                }
            }
        }

        $worksheetXml = simplexml_load_string($worksheetRaw);
        if ($worksheetXml === false || ! isset($worksheetXml->sheetData->row)) {
            throw new RuntimeException('XLSX worksheet is empty.');
        }

        $headers = [];
        $rows = [];

        foreach ($worksheetXml->sheetData->row as $rowNode) {
            $cells = [];
            foreach ($rowNode->c as $cell) {
                $type = (string) ($cell['t'] ?? '');
                $value = trim((string) ($cell->v ?? ''));

                if ($type === 's' && $value !== '') {
                    $sharedIndex = (int) $value;
                    $value = (string) ($sharedStrings[$sharedIndex] ?? '');
                }

                $cells[] = $value;
            }

            if ($headers === []) {
                $headers = $this->normalizeHeaders($cells);

                continue;
            }

            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $rows[] = $this->toAssociativeRow($headers, $cells);
        }

        if ($headers === []) {
            throw new RuntimeException('Import file has no headers.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function detectCsvDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 1024);

        $delimiters = [',', ';', "\t"];
        $bestDelimiter = ',';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($sample, $delimiter);

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    private function toAssociativeRow(array $headers, array $row): array
    {
        $result = [];

        foreach ($headers as $index => $header) {
            $result[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $result;
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return collect($headers)
            ->map(fn ($header): string => trim((string) $header))
            ->map(fn (string $header, int $index): string => $header !== '' ? $header : "column_{$index}")
            ->values()
            ->all();
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseDate(string $rawDate): string
    {
        $formats = ['d-m-Y', 'Y-m-d', 'd/m/Y'];

        foreach ($formats as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $rawDate)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Invalid transaction date.');
    }

    private function parseAmount(string $rawAmount): string
    {
        $normalized = str_replace([' ', ','], ['', '.'], $rawAmount);
        if (! is_numeric($normalized)) {
            throw new RuntimeException('Invalid transaction amount.');
        }

        return TransactionDedupe::amountToDecimalString($normalized);
    }
}

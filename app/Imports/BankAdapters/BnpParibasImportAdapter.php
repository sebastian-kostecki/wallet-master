<?php

declare(strict_types=1);

namespace App\Imports\BankAdapters;

use App\Enums\Bank;
use App\Imports\ParsedImportRow;
use App\Support\Imports\AmountParser;
use App\Support\Imports\DateParser;
use App\Support\Imports\SignBasedSubjectColumnResolver;
use App\Support\Imports\SubjectSanitizer;
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

        $data = [
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
        ];

        $sender = $this->findHeader($headers, 'Nadawca');
        if ($sender !== null) {
            $data['subject_positive'] = $sender;
        }

        $recipient = $this->findHeader($headers, 'Odbiorca');
        if ($recipient !== null) {
            $data['subject_negative'] = $recipient;
        }

        return $data;
    }

    public function normalizeRow(array $row, array $mapping): ParsedImportRow
    {
        $dateRaw = trim((string) Arr::get($row, $mapping['date'], ''));
        $amountRaw = trim((string) Arr::get($row, $mapping['amount'], ''));
        $descriptionRaw = $this->resolveDescription($row, $mapping);

        if ($dateRaw === '' || $amountRaw === '' || $descriptionRaw === '') {
            throw new RuntimeException('Required import columns are empty.');
        }

        $date = DateParser::parse($dateRaw);
        $amount = AmountParser::parse($amountRaw);
        $subject = $this->resolveSubject($row, $mapping, $amount);

        return new ParsedImportRow(
            date: $date,
            amount: $amount,
            description: Str::limit($descriptionRaw, 2000, ''),
            subject: $subject,
            rawStatementDescription: Str::limit($descriptionRaw, 2000, ''),
        );
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    private function resolveSubject(array $row, array $mapping, string $parsedAmount): ?string
    {
        $raw = $this->resolveSubjectRaw($row, $mapping, $parsedAmount);

        if ($raw === '') {
            return null;
        }

        $sanitized = SubjectSanitizer::sanitize($raw);

        if ($sanitized === '') {
            return null;
        }

        return Str::limit($sanitized, 255, '');
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    private function resolveSubjectRaw(array $row, array $mapping, string $parsedAmount): string
    {
        if (isset($mapping['subject'])) {
            return trim((string) Arr::get($row, $mapping['subject'], ''));
        }

        $columnKey = SignBasedSubjectColumnResolver::resolveColumnKey($mapping, $parsedAmount);

        if ($columnKey === null) {
            return '';
        }

        return trim((string) Arr::get($row, $columnKey, ''));
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

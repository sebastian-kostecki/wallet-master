<?php

declare(strict_types=1);

namespace App\Support\Deposits;

use App\Imports\ParsedImportRow;

final class BnpDepositRules
{
    private const OPEN_TYPE = 'OTWARCIE LOKATY';

    private const CLOSE_TYPE = 'SPŁATA DEPOZYTU';

    /**
     * @param  array<string, string>  $row
     */
    public static function classify(array $row, ParsedImportRow $parsed): DepositClassification
    {
        $transactionType = self::normalizeTransactionType(self::resolveTransactionType($row));

        if ($transactionType === self::OPEN_TYPE && bccomp($parsed->amount, '0', 2) < 0) {
            return DepositClassification::with(
                DepositClassificationIntent::Open,
                DepositClassificationConfidence::Auto,
            );
        }

        if ($transactionType === self::CLOSE_TYPE && bccomp($parsed->amount, '0', 2) > 0) {
            return DepositClassification::with(
                DepositClassificationIntent::ClosePrincipal,
                DepositClassificationConfidence::Auto,
            );
        }

        return DepositClassification::none();
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function resolveTransactionType(array $row): string
    {
        foreach (array_keys($row) as $header) {
            if (mb_strtolower(trim($header)) === 'typ transakcji') {
                return trim((string) ($row[$header] ?? ''));
            }
        }

        return '';
    }

    private static function normalizeTransactionType(string $transactionType): string
    {
        return mb_strtoupper(trim($transactionType));
    }
}

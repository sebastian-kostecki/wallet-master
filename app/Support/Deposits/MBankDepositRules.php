<?php

declare(strict_types=1);

namespace App\Support\Deposits;

use App\Imports\ParsedImportRow;

final class MBankDepositRules
{
    private const DEPOSIT_CATEGORY = 'Lokaty i konto oszcz.';

    /**
     * @param  array<string, string>  $row
     */
    public static function classify(array $row, ParsedImportRow $parsed): DepositClassification
    {
        if (self::resolveCategory($row) !== self::DEPOSIT_CATEGORY) {
            return DepositClassification::none();
        }

        if (bccomp($parsed->amount, '0', 2) < 0) {
            return DepositClassification::with(
                DepositClassificationIntent::Open,
                DepositClassificationConfidence::Auto,
            );
        }

        if (bccomp($parsed->amount, '0', 2) > 0) {
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
    private static function resolveCategory(array $row): string
    {
        foreach (array_keys($row) as $header) {
            if (mb_strtolower(trim($header)) === 'kategoria') {
                return trim($row[$header]);
            }
        }

        return '';
    }
}

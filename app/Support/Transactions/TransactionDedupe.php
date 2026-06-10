<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use Illuminate\Support\Str;

final class TransactionDedupe
{
    public static function normalizeDescription(string $description): string
    {
        $normalized = (string) Str::of($description)
            ->trim()
            ->lower()
            ->replaceMatches('/\s+/u', ' ');

        return Str::limit($normalized, 255, '');
    }

    /**
     * @return numeric-string
     */
    public static function amountToDecimalString(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param  numeric-string  $amountDecimalString
     */
    public static function dedupeHash(string $dateYmd, string $amountDecimalString, string $normalizedDescription): string
    {
        return md5($dateYmd.'|'.$amountDecimalString.'|'.$normalizedDescription, true);
    }

    /**
     * @param  numeric-string  $amountDecimalString
     */
    public static function importFingerprint(
        int $accountId,
        string $dateYmd,
        string $amountDecimalString,
        string $normalizedRawStatementDescription,
    ): string {
        return md5($accountId.'|'.$dateYmd.'|'.$amountDecimalString.'|'.$normalizedRawStatementDescription, true);
    }

    /**
     * Unique hash for manual entries so identical rows are not blocked by account dedupe index.
     *
     * @param  numeric-string  $amountDecimalString
     */
    public static function manualDedupeHash(string $dateYmd, string $amountDecimalString, string $normalizedDescription): string
    {
        return md5($dateYmd.'|'.$amountDecimalString.'|'.$normalizedDescription.'|'.Str::uuid(), true);
    }
}

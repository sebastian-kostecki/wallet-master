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

    public static function amountToDecimalString(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function dedupeHash(string $dateYmd, string $amountDecimalString, string $normalizedDescription): string
    {
        return md5($dateYmd.'|'.$amountDecimalString.'|'.$normalizedDescription, true);
    }
}


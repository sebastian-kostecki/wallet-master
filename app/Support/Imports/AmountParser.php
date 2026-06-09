<?php

declare(strict_types=1);

namespace App\Support\Imports;

use App\Support\Transactions\TransactionDedupe;
use RuntimeException;

final class AmountParser
{
    /**
     * @return numeric-string
     */
    public static function parse(string $raw): string
    {
        $normalized = trim($raw);

        if ($normalized === '') {
            throw new RuntimeException('Invalid transaction amount.');
        }

        $negative = false;

        if (preg_match('/^\((.*)\)$/u', $normalized, $matches) === 1) {
            $negative = true;
            $normalized = trim($matches[1]);
        }

        $normalized = str_ireplace(['PLN', 'zł', 'EUR', 'USD'], '', $normalized);
        $normalized = str_replace(["\xC2\xA0", ' ', "'", '`'], '', $normalized);
        $normalized = trim($normalized);

        if ($normalized === '') {
            throw new RuntimeException('Invalid transaction amount.');
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    $normalized = str_replace('.', '', $normalized);
                    $normalized = str_replace(',', '.', $normalized);
                } else {
                    $normalized = str_replace(',', '', $normalized);
                }
            }
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new RuntimeException('Invalid transaction amount.');
        }

        if (bccomp($normalized, '0', 2) === 0) {
            throw new RuntimeException('Transaction amount cannot be zero.');
        }

        if ($negative && bccomp($normalized, '0', 2) === 1) {
            $normalized = '-'.$normalized;
        }

        return TransactionDedupe::amountToDecimalString($normalized);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Imports;

use Carbon\CarbonImmutable;
use RuntimeException;

final class DateParser
{
    /**
     * @return string Date in Y-m-d format.
     */
    public static function parse(string $raw): string
    {
        $normalized = trim($raw);

        if ($normalized === '') {
            throw new RuntimeException('Invalid transaction date.');
        }

        if (preg_match('/^(.+?)\s+\d{1,2}:\d{2}(:\d{2})?$/u', $normalized, $matches) === 1) {
            $normalized = trim($matches[1]);
        }

        $formats = ['d-m-Y', 'Y-m-d', 'd/m/Y', 'd.m.Y', 'Y.m.d', 'Y/m/d'];

        foreach ($formats as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $normalized)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Invalid transaction date.');
    }
}

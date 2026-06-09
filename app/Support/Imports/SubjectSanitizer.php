<?php

declare(strict_types=1);

namespace App\Support\Imports;

final class SubjectSanitizer
{
    public static function sanitize(string $text, int $minDigitRunLength = 6): string
    {
        $pattern = '/\d{'.$minDigitRunLength.',}/u';
        $withoutLongDigits = preg_replace($pattern, '', $text) ?? $text;
        $collapsed = preg_replace('/\s+/u', ' ', $withoutLongDigits) ?? $withoutLongDigits;

        return trim($collapsed);
    }
}

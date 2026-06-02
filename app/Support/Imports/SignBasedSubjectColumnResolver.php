<?php

declare(strict_types=1);

namespace App\Support\Imports;

final class SignBasedSubjectColumnResolver
{
    /**
     * @param  array{subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    public static function resolveColumnKey(array $mapping, string $parsedAmount): ?string
    {
        if (isset($mapping['subject'])) {
            return null;
        }

        $positive = $mapping['subject_positive'] ?? null;
        $negative = $mapping['subject_negative'] ?? null;

        if ($positive === null && $negative === null) {
            return null;
        }

        $numericAmount = (float) $parsedAmount;

        if ($numericAmount > 0) {
            return $positive;
        }

        if ($numericAmount < 0) {
            return $negative;
        }

        return null;
    }
}

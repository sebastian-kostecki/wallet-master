<?php

declare(strict_types=1);

namespace App\Support\DescriptionMemory;

use Illuminate\Support\Str;

final class RawStatementDescriptionNormalizer
{
    public static function normalizeStrict(string $raw): string
    {
        return Str::of($raw)->lower()->squish()->toString();
    }
}

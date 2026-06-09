<?php

declare(strict_types=1);

namespace App\Imports;

final class TransferMatcherResult
{
    public function __construct(
        public int $autoLinked = 0,
        public int $manualLinked = 0,
        public int $ambiguousSkipped = 0,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

final class ImportCommitCounters
{
    public int $rowsTotal = 0;

    public int $rowsImported = 0;

    public int $rowsSkippedDuplicate = 0;

    public int $rowsFailedValidation = 0;

    /** @var numeric-string */
    public string $importedAmountSum = '0.00';

    public int $rowIndex = 0;
}

<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

use App\Models\Import;

final readonly class QueueImportCommitResult
{
    public function __construct(
        public QueueImportCommitStatus $status,
        public ?Import $import = null,
    ) {}
}

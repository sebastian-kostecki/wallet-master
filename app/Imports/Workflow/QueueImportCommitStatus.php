<?php

declare(strict_types=1);

namespace App\Imports\Workflow;

enum QueueImportCommitStatus
{
    case Queued;

    case MissingMapping;

    case NotDraft;
}

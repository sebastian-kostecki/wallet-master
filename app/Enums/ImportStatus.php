<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case Draft = 'draft';

    case Queued = 'queued';

    case Processing = 'processing';

    case Committed = 'committed';

    case Failed = 'failed';
}

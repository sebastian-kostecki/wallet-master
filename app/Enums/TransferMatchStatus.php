<?php

declare(strict_types=1);

namespace App\Enums;

enum TransferMatchStatus: string
{
    case None = 'none';
    case Auto = 'auto';
    case Manual = 'manual';
    case Rejected = 'rejected';
}

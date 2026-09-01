<?php

declare(strict_types=1);

namespace App\Support\Deposits;

enum DepositClassificationConfidence: string
{
    case Auto = 'auto';
    case Candidate = 'candidate';
}

<?php

declare(strict_types=1);

namespace App\Support\Deposits;

enum DepositClassificationIntent: string
{
    case None = 'none';
    case Open = 'open';
    case ClosePrincipal = 'close_principal';
}

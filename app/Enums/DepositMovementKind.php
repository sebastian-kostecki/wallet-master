<?php

declare(strict_types=1);

namespace App\Enums;

enum DepositMovementKind: string
{
    case Open = 'open';
    case ClosePrincipal = 'close_principal';
    case Interest = 'interest';
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum PocketMovement: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
}

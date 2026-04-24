<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case Ror = 'ror';
    case Savings = 'savings';

    public function label(): string
    {
        return match ($this) {
            self::Ror => 'ROR',
            self::Savings => 'Oszczędnościowe',
        };
    }
}

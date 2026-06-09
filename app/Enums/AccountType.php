<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';

    public function labelKey(): string
    {
        return "accounts.enums.accountType.{$this->value}";
    }

    public function iconName(): string
    {
        return match ($this) {
            self::Checking => 'creditCard',
            self::Savings => 'piggyBank',
        };
    }
}

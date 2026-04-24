<?php

declare(strict_types=1);

namespace App\Enums;

enum Bank: string
{
    case BnpParibas = 'bnp-paribas';
    case MBank = 'mbank';
    case Cash = 'cash';

    public function labelKey(): string
    {
        return "accounts.enums.bank.{$this->value}";
    }

    public function supportsImport(): bool
    {
        return $this !== self::Cash;
    }
}

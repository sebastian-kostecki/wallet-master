<?php

declare(strict_types=1);

namespace App\Enums;

enum Bank: string
{
    case BnpParibas = 'bnp-paribas';
    case MBank = 'mbank';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::BnpParibas => 'BNP Paribas',
            self::MBank => 'mBank',
            self::Cash => 'Gotówka',
        };
    }

    public function supportsImport(): bool
    {
        return $this !== self::Cash;
    }
}

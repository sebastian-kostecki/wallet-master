<?php

declare(strict_types=1);

namespace App\Enums;

use App\Imports\BankAdapters\BankImportAdapter;
use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\BankAdapters\MBankImportAdapter;
use RuntimeException;

enum Bank: string
{
    case BnpParibas = 'bnp-paribas';
    case MBank = 'mbank';
    case Cash = 'cash';

    public function labelKey(): string
    {
        return "accounts.enums.bank.{$this->value}";
    }

    public function bankIconUrl(): ?string
    {
        $relative = $this->bankIconRelativePath();

        return $relative !== null ? asset($relative) : null;
    }

    public function bankIconRelativePath(): ?string
    {
        return match ($this) {
            self::Cash => null,
            default => "icons/banks/{$this->value}.jpeg",
        };
    }

    /**
     * @return class-string<BankImportAdapter>|null
     */
    public function importAdapterClass(): ?string
    {
        return match ($this) {
            self::BnpParibas => BnpParibasImportAdapter::class,
            self::MBank => MBankImportAdapter::class,
            self::Cash => null,
        };
    }

    public function supportsImport(): bool
    {
        return $this->importAdapterClass() !== null;
    }

    public function makeImportAdapter(): BankImportAdapter
    {
        $class = $this->importAdapterClass();

        if ($class === null) {
            throw new RuntimeException('This bank does not support imports.');
        }

        return new $class;
    }

    /**
     * @return list<string>
     */
    public static function importableValues(): array
    {
        $values = [];

        foreach (self::cases() as $bank) {
            if ($bank->supportsImport()) {
                $values[] = $bank->value;
            }
        }

        return $values;
    }

    /**
     * @return list<Bank>
     */
    public static function importableCases(): array
    {
        $banks = [];

        foreach (self::cases() as $bank) {
            if ($bank->supportsImport()) {
                $banks[] = $bank;
            }
        }

        return $banks;
    }
}

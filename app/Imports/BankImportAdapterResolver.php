<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\Bank;
use App\Imports\BankAdapters\BankImportAdapter;
use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\BankAdapters\MBankImportAdapter;
use RuntimeException;

final class BankImportAdapterResolver
{
    public function resolve(Bank $bank): BankImportAdapter
    {
        return match ($bank) {
            Bank::BnpParibas => new BnpParibasImportAdapter,
            Bank::MBank => new MBankImportAdapter,
            default => throw new RuntimeException('This account bank does not support imports.'),
        };
    }
}

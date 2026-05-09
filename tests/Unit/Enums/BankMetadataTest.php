<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\BankAdapters\MBankImportAdapter;

it('marks only banks with adapters as importable', function () {
    expect(Bank::Cash->supportsImport())->toBeFalse()
        ->and(Bank::Cash->importAdapterClass())->toBeNull()
        ->and(Bank::BnpParibas->supportsImport())->toBeTrue()
        ->and(Bank::MBank->supportsImport())->toBeTrue();
});

it('exposes importable backing values in enum declaration order', function () {
    expect(Bank::importableValues())->toBe(['bnp-paribas', 'mbank'])
        ->and(Bank::importableCases())->toBe([Bank::BnpParibas, Bank::MBank]);
});

it('maps each importable bank to the correct adapter class', function () {
    expect(Bank::BnpParibas->importAdapterClass())->toBe(BnpParibasImportAdapter::class)
        ->and(Bank::MBank->importAdapterClass())->toBe(MBankImportAdapter::class);
});

it('does not expose an icon path for cash accounts', function () {
    expect(Bank::Cash->bankIconRelativePath())->toBeNull()
        ->and(Bank::Cash->bankIconUrl())->toBeNull();
});

it('exposes a jpeg icon path for non-cash banks', function () {
    expect(Bank::MBank->bankIconRelativePath())->toBe('icons/banks/mbank.jpeg');
});

it('throws when building an import adapter for a non-importable bank', function () {
    Bank::Cash->makeImportAdapter();
})->throws(RuntimeException::class, 'This bank does not support imports.');

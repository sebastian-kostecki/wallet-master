<?php

declare(strict_types=1);

use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\BankAdapters\MBankImportAdapter;

it('resolves BNP Paribas default mapping with subject when present', function () {
    $adapter = new BnpParibasImportAdapter;

    $mapping = $adapter->defaultMapping(['date', 'amount', 'description', 'subject']);

    expect($mapping)->toBe([
        'date' => 'date',
        'amount' => 'amount',
        'description' => 'description',
        'subject' => 'subject',
    ]);
});

it('resolves BNP Paribas default mapping case-insensitively without subject', function () {
    $adapter = new BnpParibasImportAdapter;

    $mapping = $adapter->defaultMapping(['Date', 'Amount', 'Description']);

    expect($mapping)->toBe([
        'date' => 'Date',
        'amount' => 'Amount',
        'description' => 'Description',
    ]);
});

it('returns null for BNP Paribas adapter when description column is missing', function () {
    $adapter = new BnpParibasImportAdapter;

    expect($adapter->defaultMapping(['date', 'amount']))->toBeNull();
});

it('resolves mBank default mapping with category as subject', function () {
    $adapter = new MBankImportAdapter;

    $mapping = $adapter->defaultMapping(['Data operacji', 'Opis operacji', 'Rachunek', 'Kategoria', 'Kwota']);

    expect($mapping)->toBe([
        'date' => 'Data operacji',
        'amount' => 'Kwota',
        'description' => 'Opis operacji',
        'subject' => 'Kategoria',
    ]);
});

it('returns null for mBank adapter when amount column is missing', function () {
    $adapter = new MBankImportAdapter;

    expect($adapter->defaultMapping(['Data operacji', 'Opis operacji', 'Kategoria']))->toBeNull();
});

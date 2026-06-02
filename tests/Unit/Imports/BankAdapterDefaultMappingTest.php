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

it('uses Typ transakcji for BNP Paribas when Opis column is missing', function () {
    $adapter = new BnpParibasImportAdapter;

    $mapping = $adapter->defaultMapping(['Data transakcji', 'Kwota', 'Typ transakcji']);

    expect($mapping)->toBe([
        'date' => 'Data transakcji',
        'amount' => 'Kwota',
        'description' => 'Typ transakcji',
    ]);
});

it('maps BNP Paribas Nadawca and Odbiorca as sign-based subject columns', function () {
    $mapping = (new BnpParibasImportAdapter)->defaultMapping([
        'Data transakcji', 'Kwota', 'Opis', 'Nadawca', 'Odbiorca',
    ]);

    expect($mapping)->toBe([
        'date' => 'Data transakcji',
        'amount' => 'Kwota',
        'description' => 'Opis',
        'subject_positive' => 'Nadawca',
        'subject_negative' => 'Odbiorca',
    ]);
});

it('prefers Opis over Typ transakcji for BNP Paribas default mapping when both exist', function () {
    $adapter = new BnpParibasImportAdapter;

    $mapping = $adapter->defaultMapping(['Data transakcji', 'Kwota', 'Opis', 'Typ transakcji']);

    expect($mapping)->toBe([
        'date' => 'Data transakcji',
        'amount' => 'Kwota',
        'description' => 'Opis',
    ]);
});

it('returns null for BNP Paribas adapter when no description column is available', function () {
    $adapter = new BnpParibasImportAdapter;

    expect($adapter->defaultMapping(['date', 'amount']))->toBeNull();
    expect($adapter->defaultMapping(['Data transakcji', 'Kwota']))->toBeNull();
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

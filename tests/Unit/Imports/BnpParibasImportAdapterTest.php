<?php

declare(strict_types=1);

use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\ParsedImportRow;

it('normalizes row using Opis when present', function () {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '24-04-2026',
            'Kwota' => '-12,34',
            'Opis' => 'Coffee shop',
            'Typ transakcji' => 'Karta debetowa',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
        ],
    );

    expect($parsed)->toBeInstanceOf(ParsedImportRow::class)
        ->and($parsed->description)->toBe('Coffee shop')
        ->and($parsed->rawStatementDescription)->toBe('Coffee shop');
});

it('falls back to Typ transakcji when Opis is empty', function () {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '25-04-2026',
            'Kwota' => '100,00',
            'Opis' => '',
            'Typ transakcji' => 'Przelew przychodzący',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
        ],
    );

    expect($parsed->description)->toBe('Przelew przychodzący')
        ->and($parsed->rawStatementDescription)->toBe('Przelew przychodzący');
});

it('throws when both Opis and Typ transakcji are empty', function () {
    $adapter = new BnpParibasImportAdapter;

    $adapter->normalizeRow(
        [
            'Data transakcji' => '26-04-2026',
            'Kwota' => '-5,00',
            'Opis' => '',
            'Typ transakcji' => '',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
        ],
    );
})->throws(RuntimeException::class, 'Required import columns are empty.');

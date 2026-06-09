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

$plMapping = [
    'date' => 'Data transakcji',
    'amount' => 'Kwota',
    'description' => 'Opis',
    'subject_positive' => 'Nadawca',
    'subject_negative' => 'Odbiorca',
];

it('uses Nadawca as subject for positive amounts and strips long digits', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '24-04-2026',
            'Kwota' => '100,00',
            'Opis' => 'Przelew',
            'Nadawca' => '123456789012 JAN KOWALSKI',
            'Odbiorca' => '',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBe('JAN KOWALSKI');
});

it('uses Odbiorca as subject for negative amounts', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '25-04-2026',
            'Kwota' => '-12,34',
            'Opis' => 'Zakup',
            'Nadawca' => 'SHOULD NOT USE',
            'Odbiorca' => '987654321098 SKLEP',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBe('SKLEP');
});

it('returns null subject when chosen column is empty without fallback', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '26-04-2026',
            'Kwota' => '50,00',
            'Opis' => 'Opłata',
            'Nadawca' => '',
            'Odbiorca' => 'ONLY ODBIORCA',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBeNull();
});

it('returns null subject for zero amount', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;
    $resolveSubject = new ReflectionMethod(BnpParibasImportAdapter::class, 'resolveSubject');
    $resolveSubject->setAccessible(true);

    $subject = $resolveSubject->invoke($adapter, [
        'Nadawca' => 'A',
        'Odbiorca' => 'B',
    ], $plMapping, '0.00');

    expect($subject)->toBeNull();
});

it('sanitizes manual mapping.subject column', function () {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '28-04-2026',
            'Kwota' => '-5,00',
            'Opis' => 'Test',
            'subject' => '111111 Jan',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
            'subject' => 'subject',
        ],
    );

    expect($parsed->subject)->toBe('Jan');
});

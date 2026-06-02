<?php

declare(strict_types=1);

use App\Support\Imports\ImportRowRawSnapshot;

it('stores raw subject from Nadawca for positive BNP mapping without sanitizing digits', function () {
    $snapshot = ImportRowRawSnapshot::fromMappedRow(
        [
            'Kwota' => '100,00',
            'Nadawca' => '123456789012 JAN',
            'Odbiorca' => 'IGNORED',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
            'subject_positive' => 'Nadawca',
            'subject_negative' => 'Odbiorca',
        ],
    );

    expect($snapshot['subject_raw'])->toBe('123456789012 JAN');
});

it('stores raw subject from Odbiorca for negative amounts', function () {
    $snapshot = ImportRowRawSnapshot::fromMappedRow(
        [
            'Kwota' => '-10,00',
            'Nadawca' => 'IGNORED',
            'Odbiorca' => 'RAW SHOP',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
            'subject_positive' => 'Nadawca',
            'subject_negative' => 'Odbiorca',
        ],
    );

    expect($snapshot['subject_raw'])->toBe('RAW SHOP');
});

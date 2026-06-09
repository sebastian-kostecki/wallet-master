<?php

declare(strict_types=1);

use App\Support\Imports\SignBasedSubjectColumnResolver;

$mapping = [
    'subject_positive' => 'Nadawca',
    'subject_negative' => 'Odbiorca',
];

it('returns positive column header for positive amounts', function () use ($mapping) {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey($mapping, '100.50'))
        ->toBe('Nadawca');
});

it('returns negative column header for negative amounts', function () use ($mapping) {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey($mapping, '-42.00'))
        ->toBe('Odbiorca');
});

it('returns null for zero amount', function () use ($mapping) {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey($mapping, '0'))
        ->toBeNull();
});

it('returns null when mapping uses single subject column only', function () {
    $singleSubjectMapping = [
        'subject' => 'Opis',
        'subject_positive' => 'Nadawca',
        'subject_negative' => 'Odbiorca',
    ];

    expect(SignBasedSubjectColumnResolver::resolveColumnKey($singleSubjectMapping, '100'))
        ->toBeNull();
});

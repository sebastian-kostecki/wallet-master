<?php

declare(strict_types=1);

use App\Support\Imports\SubjectSanitizer;

it('strips digit runs of six or more', function () {
    expect(SubjectSanitizer::sanitize('123456789012 JAN KOWALSKI'))->toBe('JAN KOWALSKI');
});

it('keeps short digits', function () {
    expect(SubjectSanitizer::sanitize('Firma 3M'))->toBe('Firma 3M');
});

it('collapses whitespace', function () {
    expect(SubjectSanitizer::sanitize('  123456   SKLEP   '))->toBe('SKLEP');
});

it('returns empty when only digits', function () {
    expect(SubjectSanitizer::sanitize('123456789012'))->toBe('');
});

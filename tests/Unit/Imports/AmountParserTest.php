<?php

declare(strict_types=1);

use App\Support\Imports\AmountParser;

test('amount parser accepts polish and accounting formats', function (string $raw, string $expected) {
    expect(AmountParser::parse($raw))->toBe($expected);
})->with([
    'comma decimal' => ['1234,56', '1234.56'],
    'thousands dot' => ['1.234,56', '1234.56'],
    'thousands space' => ['1 234,56', '1234.56'],
    'dot decimal' => ['1234.56', '1234.56'],
    'accounting negative' => ['(123,45)', '-123.45'],
    'pln suffix' => ['1234,56 PLN', '1234.56'],
    'negative sign' => ['-99,00', '-99.00'],
]);

test('amount parser rejects zero', function () {
    AmountParser::parse('0,00');
})->throws(RuntimeException::class);

<?php

declare(strict_types=1);

use App\Support\Imports\DateParser;

test('date parser accepts supported formats', function (string $raw, string $expected) {
    expect(DateParser::parse($raw))->toBe($expected);
})->with([
    'd-m-Y' => ['01-04-2026', '2026-04-01'],
    'Y-m-d' => ['2026-04-01', '2026-04-01'],
    'd/m/Y' => ['01/04/2026', '2026-04-01'],
    'd.m.Y' => ['01.04.2026', '2026-04-01'],
    'Y.m.d' => ['2026.04.01', '2026-04-01'],
    'Y/m/d' => ['2026/04/01', '2026-04-01'],
    'with time suffix' => ['2026-04-01 12:34:56', '2026-04-01'],
]);

test('date parser rejects invalid input', function () {
    DateParser::parse('not-a-date');
})->throws(RuntimeException::class);

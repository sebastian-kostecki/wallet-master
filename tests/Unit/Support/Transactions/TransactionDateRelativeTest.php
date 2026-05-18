<?php

use App\Models\Transaction;
use App\Support\Transactions\TransactionDateRelative;
use Carbon\CarbonImmutable;

test('displayDateIso prefers booked_at when set', function () {
    $transaction = new Transaction([
        'date' => '2026-04-01',
        'booked_at' => '2026-04-12',
    ]);

    expect(TransactionDateRelative::displayDateIso($transaction))->toBe('2026-04-12');
});

test('displayDateIso falls back to date when booked_at is null', function () {
    $transaction = new Transaction([
        'date' => '2026-04-01',
        'booked_at' => null,
    ]);

    expect(TransactionDateRelative::displayDateIso($transaction))->toBe('2026-04-01');
});

test('format returns dzisiaj for today in polish locale', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 12, 12, 0, 0));
    app()->setLocale('pl');

    $transaction = new Transaction([
        'date' => '2026-04-01',
        'booked_at' => '2026-04-12',
    ]);

    expect(TransactionDateRelative::format($transaction))->toBe('dzisiaj');

    CarbonImmutable::setTestNow();
});

test('format returns today for today in english locale', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 12, 12, 0, 0));
    app()->setLocale('en');

    $transaction = new Transaction([
        'date' => '2026-04-01',
        'booked_at' => '2026-04-12',
    ]);

    expect(TransactionDateRelative::format($transaction))->toBe('today');

    CarbonImmutable::setTestNow();
});

test('format returns diffForHumans for other dates', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 12, 12, 0, 0));
    app()->setLocale('pl');

    $transaction = new Transaction([
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
    ]);

    expect(TransactionDateRelative::format($transaction))->toBe('2 dni temu');

    CarbonImmutable::setTestNow();
});

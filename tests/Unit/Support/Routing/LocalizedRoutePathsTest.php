<?php

declare(strict_types=1);

use App\Support\Routing\LocalizedRoutePaths;
use Tests\TestCase;

uses(TestCase::class);

test('get returns polish segment for known key', function () {
    expect(LocalizedRoutePaths::get('transactions'))->toBe('transakcje');
    expect(LocalizedRoutePaths::get('budget.monthly'))->toBe('budzet/miesieczny');
});

test('get returns key unchanged when missing from map', function () {
    expect(LocalizedRoutePaths::get('telemetry.event'))->toBe('telemetry.event');
});

test('route_path helper delegates to LocalizedRoutePaths', function () {
    expect(route_path('accounts'))->toBe('konta');
    expect(route_path('categories.estimates.yearly-plan'))->toBe('kategorie/{category}/szacunki/plan-roczny');
});

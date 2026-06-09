<?php

use App\Models\Currency;
use Illuminate\Support\Facades\Artisan;

test('currency:create creates a currency with provided options', function () {
    $exitCode = Artisan::call('currency:create', [
        '--code' => 'EUR',
        '--name' => 'Euro',
        '--symbol' => '€',
        '--precision' => 2,
    ]);

    expect($exitCode)->toBe(0);

    $currency = Currency::query()->where('code', 'EUR')->first();

    expect($currency)->not->toBeNull()
        ->and($currency->name)->toBe('Euro')
        ->and($currency->symbol)->toBe('€')
        ->and($currency->precision)->toBe(2);
});

test('currency:create normalizes code to uppercase', function () {
    $exitCode = Artisan::call('currency:create', [
        '--code' => 'eur',
        '--name' => 'Euro',
        '--symbol' => '€',
    ]);

    expect($exitCode)->toBe(0);

    expect(Currency::query()->where('code', 'EUR')->exists())->toBeTrue()
        ->and(Currency::query()->where('code', 'eur')->exists())->toBeFalse();
});

test('currency:create fails when code already exists without update flag', function () {
    Currency::query()->create([
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'precision' => 2,
    ]);

    $exitCode = Artisan::call('currency:create', [
        '--code' => 'EUR',
        '--name' => 'Euro duplicate',
        '--symbol' => '€',
    ]);

    expect($exitCode)->toBe(1)
        ->and(Currency::query()->where('code', 'EUR')->count())->toBe(1)
        ->and(Currency::query()->where('code', 'EUR')->value('name'))->toBe('Euro');
});

test('currency:create with update flag updates existing currency', function () {
    Currency::query()->create([
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'precision' => 2,
    ]);

    $exitCode = Artisan::call('currency:create', [
        '--code' => 'EUR',
        '--name' => 'European Euro',
        '--symbol' => 'EUR',
        '--precision' => 4,
        '--update' => true,
    ]);

    expect($exitCode)->toBe(0);

    $currency = Currency::query()->where('code', 'EUR')->firstOrFail();

    expect($currency->name)->toBe('European Euro')
        ->and($currency->symbol)->toBe('EUR')
        ->and($currency->precision)->toBe(4);
});

test('currency:create fails on invalid code length', function () {
    $exitCode = Artisan::call('currency:create', [
        '--code' => 'EURO',
        '--name' => 'Euro',
        '--symbol' => '€',
    ]);

    expect($exitCode)->toBe(1)
        ->and(Currency::query()->where('code', 'EURO')->exists())->toBeFalse();
});

test('currency:create fails when name is empty', function () {
    $exitCode = Artisan::call('currency:create', [
        '--code' => 'USD',
        '--name' => '',
        '--symbol' => '$',
    ]);

    expect($exitCode)->toBe(1)
        ->and(Currency::query()->where('code', 'USD')->exists())->toBeFalse();
});

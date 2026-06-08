<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\Transaction;

test('transaction model does not declare empty guarded property', function () {
    $reflection = new \ReflectionClass(Transaction::class);

    if ($reflection->hasProperty('guarded')) {
        expect($reflection->getProperty('guarded')->getDeclaringClass()->getName())
            ->not->toBe(Transaction::class);
    }

    expect($reflection->getDefaultProperties()['fillable'] ?? [])->toContain('user_id');
});

test('currency model uses explicit fillable fields', function () {
    $currency = new Currency;

    expect($currency->getFillable())->toBe(['code', 'name', 'symbol', 'precision']);
});

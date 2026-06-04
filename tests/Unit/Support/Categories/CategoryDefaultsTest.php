<?php

use App\Support\Categories\CategoryDefaults;

test('starter rows include groceries with icon and color', function () {
    $rows = CategoryDefaults::starterRows();
    $groceries = collect($rows)->firstWhere('name', 'Artykuły spożywcze');

    expect($groceries)->not->toBeNull()
        ->and($groceries['icon'])->toBe('shopping-cart')
        ->and($groceries['color'])->toBe('#ef4444');
});

test('starter rows do not include system savings category', function () {
    $rows = CategoryDefaults::starterRows();
    $savings = collect($rows)->first(fn ($r) => ($r['name'] ?? '') === 'Oszczędności');

    expect($savings)->toBeNull();
    expect(collect($rows)->where('is_system', true))->toBeEmpty();
});

test('starter rows count is twenty five', function () {
    expect(CategoryDefaults::starterRows())->toHaveCount(25);
});

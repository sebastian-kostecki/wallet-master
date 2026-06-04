<?php

use App\Support\Categories\CategoryDefaults;

test('starter rows include groceries with icon and color', function () {
    $rows = CategoryDefaults::starterRows();
    $groceries = collect($rows)->firstWhere('name', 'Artykuły spożywcze');

    expect($groceries)->not->toBeNull()
        ->and($groceries['icon'])->toBe('shopping-cart')
        ->and($groceries['color'])->toBe('#ef4444');
});

test('starter rows include system savings category', function () {
    $rows = CategoryDefaults::starterRows();
    $savings = collect($rows)->first(fn ($r) => ($r['name'] ?? '') === 'Oszczędności' && ($r['is_system'] ?? false));

    expect($savings)->not->toBeNull()
        ->and($savings)->toHaveKeys(['icon', 'color', 'type', 'sort_order']);
});

test('starter rows count is twenty six', function () {
    expect(CategoryDefaults::starterRows())->toHaveCount(26);
});

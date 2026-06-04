<?php

use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

test('category colors contains seed hex values', function () {
    expect(CategoryColors::values())
        ->toHaveCount(24)
        ->toContain('#ef4444', '#10b981', '#868e96', '#22c55e', '#f43f5e');
});

test('category icons central whitelist contains seed and catalog icons', function () {
    $icons = CategoryIcons::values();

    expect($icons)
        ->toHaveCount(138)
        ->toEqual(array_values(array_unique($icons)))
        ->toContain('shopping-cart', 'briefcase', 'piggy-bank', 'tag', 'droplet', 'gamepad-2', 'store')
        ->toContain('utensils-crossed', 'theater', 'footprints', 'circle-help', 'chef-hat');
});

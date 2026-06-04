<?php

use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

test('category colors contains seed hex values', function () {
    expect(CategoryColors::values())->toContain('#ef4444', '#10b981', '#868e96');
});

test('category icons contains seed icon names', function () {
    expect(CategoryIcons::values())->toContain('shopping-cart', 'briefcase', 'piggy-bank', 'tag');
});

<?php

use App\Models\Category;
use App\Models\User;

test('new user receives starter categories on ensure', function () {
    $user = User::factory()->create();

    ensureUserCategories($user);

    expect(Category::where('user_id', $user->id)->count())->toBe(26);
    expect(Category::where('user_id', $user->id)->where('is_system', true)->where('name', 'Oszczędności')->exists())->toBeTrue();
});

test('ensure is idempotent', function () {
    $user = User::factory()->create();

    ensureUserCategories($user);
    $count = Category::where('user_id', $user->id)->count();

    ensureUserCategories($user);

    expect(Category::where('user_id', $user->id)->count())->toBe($count);
});

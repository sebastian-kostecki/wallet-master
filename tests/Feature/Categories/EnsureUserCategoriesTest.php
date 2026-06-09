<?php

use App\Models\Category;
use App\Models\User;

test('new user receives starter categories on ensure', function () {
    $user = User::factory()->create();

    ensureUserCategories($user);

    expect(Category::where('user_id', $user->id)->count())->toBe(25);
    expect(Category::where('user_id', $user->id)->where('name', 'Oszczędności')->exists())->toBeFalse();
});

test('ensure is idempotent', function () {
    $user = User::factory()->create();

    ensureUserCategories($user);
    $count = Category::where('user_id', $user->id)->count();

    ensureUserCategories($user);

    expect(Category::where('user_id', $user->id)->count())->toBe($count);
});

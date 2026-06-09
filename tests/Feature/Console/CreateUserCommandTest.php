<?php

use App\Models\Category;
use App\Models\User;
use App\Support\Categories\CategoryDefaults;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('user:create creates a verified user with a working password', function () {
    $exitCode = Artisan::call('user:create', [
        '--name' => 'CLI User',
        '--email' => 'cli-user@example.com',
        '--password' => 'password',
    ]);

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'cli-user@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('CLI User')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', (string) $user->password))->toBeTrue();
});

test('user:create with --unverified leaves email unverified', function () {
    $exitCode = Artisan::call('user:create', [
        '--name' => 'Unverified User',
        '--email' => 'unverified@example.com',
        '--password' => 'password',
        '--unverified' => true,
    ]);

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'unverified@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
});

test('user:create fails when email is already taken', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $exitCode = Artisan::call('user:create', [
        '--name' => 'Duplicate User',
        '--email' => 'existing@example.com',
        '--password' => 'password',
    ]);

    expect($exitCode)->toBe(1)
        ->and(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

test('user:create seeds starter categories for the new user', function () {
    $exitCode = Artisan::call('user:create', [
        '--name' => 'Category User',
        '--email' => 'categories@example.com',
        '--password' => 'password',
    ]);

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'categories@example.com')->firstOrFail();

    expect(Category::query()->where('user_id', $user->id)->count())
        ->toBe(count(CategoryDefaults::starterRows()));
});

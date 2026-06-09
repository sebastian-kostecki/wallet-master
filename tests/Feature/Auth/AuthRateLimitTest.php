<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('login is rate limited to six requests per minute per ip', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->post(route('login', absolute: false), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login', absolute: false), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('forgot password is rate limited to six requests per minute per ip', function () {
    Notification::fake();

    $user = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->post(route('password.email', absolute: false), ['email' => $user->email])->assertSessionHasNoErrors();
    }

    $this->post(route('password.email', absolute: false), ['email' => $user->email])->assertStatus(429);
});

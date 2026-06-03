<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

test('registration records user_registered telemetry event', function () {
    Log::fake();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) {
        return $message === 'user_registered'
            && $context['event'] === 'user_registered'
            && isset($context['user_id'])
            && isset($context['recorded_at']);
    });
});

test('login success records user_logged_in telemetry event', function () {
    Log::fake();

    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user) {
        return $message === 'user_logged_in'
            && $context['event'] === 'user_logged_in'
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });
});

test('login failure records user_login_failed telemetry event without email', function () {
    Log::fake();

    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) {
        return $message === 'user_login_failed'
            && $context['event'] === 'user_login_failed'
            && isset($context['ip'])
            && ! array_key_exists('email', $context)
            && isset($context['recorded_at']);
    });
});

test('password reset request records password_reset_requested telemetry event', function () {
    Log::fake();
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) {
        return $message === 'password_reset_requested'
            && $context['event'] === 'password_reset_requested'
            && isset($context['recorded_at']);
    });
});

test('password reset complete records password_reset_completed telemetry event', function () {
    Log::fake();
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user) {
            return $message === 'password_reset_completed'
                && $context['event'] === 'password_reset_completed'
                && $context['user_id'] === $user->id
                && isset($context['recorded_at']);
        });

        return true;
    });
});

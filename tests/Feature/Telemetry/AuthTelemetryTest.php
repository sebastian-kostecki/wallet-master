<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('registration records user_registered telemetry event', function () {
    config(['auth.registration.enabled' => true]);

    $logged = captureTelemetryLogs(function (): void {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    });

    assertTelemetryEvent($logged, 'user_registered', fn (array $context) => isset($context['user_id'], $context['recorded_at']));
});

test('login success records user_logged_in telemetry event', function () {
    $user = User::factory()->create();

    $logged = captureTelemetryLogs(function () use ($user): void {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    });

    assertTelemetryEvent($logged, 'user_logged_in', function (array $context) use ($user) {
        return $context['user_id'] === $user->id;
    });
});

test('login failure records user_login_failed telemetry event without email', function () {
    $user = User::factory()->create();

    $logged = captureTelemetryLogs(function () use ($user): void {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    });

    assertTelemetryEvent($logged, 'user_login_failed', fn (array $context) => isset($context['ip'], $context['recorded_at']) && ! array_key_exists('email', $context));
});

test('password reset request records password_reset_requested telemetry event', function () {
    Notification::fake();

    $user = User::factory()->create();

    $logged = captureTelemetryLogs(function () use ($user): void {
        $this->post('/forgot-password', ['email' => $user->email]);
    });

    assertTelemetryEvent($logged, 'password_reset_requested');
});

test('password reset complete records password_reset_completed telemetry event', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $logged = captureTelemetryLogs(function () use ($notification, $user): void {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
        });

        assertTelemetryEvent($logged, 'password_reset_completed', function (array $context) use ($user) {
            return $context['user_id'] === $user->id;
        });

        return true;
    });
});

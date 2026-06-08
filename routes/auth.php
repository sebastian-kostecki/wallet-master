<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::middleware('registration.enabled')->group(function () {
        Route::get(route_path('auth.register'), [RegisteredUserController::class, 'create'])
            ->name('register');

        Route::post(route_path('auth.register'), [RegisteredUserController::class, 'store']);
    });

    Route::get(route_path('auth.login'), [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post(route_path('auth.login'), [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::get(route_path('auth.password.request'), [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post(route_path('auth.password.request'), [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get(route_path('auth.password.reset'), [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post(route_path('auth.password.store'), [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get(route_path('auth.verification.notice'), EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get(route_path('auth.verification.verify'), VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post(route_path('auth.verification.send'), [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get(route_path('auth.password.confirm'), [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post(route_path('auth.password.confirm'), [ConfirmablePasswordController::class, 'store']);

    Route::post(route_path('auth.logout'), [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

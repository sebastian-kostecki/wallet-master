<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Support\Routing\LegacyRouteRedirector;
use App\Support\Routing\LocalizedRoutePaths;
use Illuminate\Support\Facades\Route;

LegacyRouteRedirector::register();

Route::post(LocalizedRoutePaths::legacy('auth.login'), [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.register'), [RegisteredUserController::class, 'store'])
    ->middleware(['guest', 'registration.enabled']);
Route::post(LocalizedRoutePaths::legacy('auth.password.request'), [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.password.store'), [NewPasswordController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.verification.send'), [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.password.confirm'), [ConfirmablePasswordController::class, 'store'])
    ->middleware('auth');
Route::post(LocalizedRoutePaths::legacy('auth.logout'), [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth');

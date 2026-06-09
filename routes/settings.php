<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect(route_path('settings'), route_path('settings.profile'));

    Route::get(route_path('settings.profile'), [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch(route_path('settings.profile'), [ProfileController::class, 'update'])->name('profile.update');
    Route::delete(route_path('settings.profile'), [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get(route_path('settings.password'), [PasswordController::class, 'edit'])->name('password.edit');
    Route::put(route_path('settings.password'), [PasswordController::class, 'update'])->name('password.update');

    Route::get(route_path('settings.appearance'), function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});

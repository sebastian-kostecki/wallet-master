<?php

use App\Http\Controllers\AccountBalanceController;
use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('accounts', AccountController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::patch('accounts/{account}/balance', [AccountBalanceController::class, 'update'])
        ->middleware('account.active')
        ->name('accounts.balance.update');
});

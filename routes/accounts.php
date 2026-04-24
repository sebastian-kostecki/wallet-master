<?php

use App\Http\Controllers\AccountBalanceController;
use App\Http\Controllers\AccountController;
use App\Models\Account;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('account', fn (string $value) => Account::withTrashed()->findOrFail($value));

    Route::resource('accounts', AccountController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware([
            'edit' => 'account.active',
            'update' => 'account.active',
            'destroy' => 'account.active',
        ]);

    Route::patch('accounts/{account}/balance', [AccountBalanceController::class, 'update'])
        ->middleware('account.active')
        ->name('accounts.balance.update');
});

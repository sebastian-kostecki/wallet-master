<?php

use App\Http\Controllers\Accounts\AccountBalanceController;
use App\Http\Controllers\Accounts\AccountController;
use App\Models\Account;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('account', fn (string $value) => Account::withTrashed()->findOrFail($value));

    Route::resource(route_path('accounts'), AccountController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('accounts')
        ->parameters([route_path('accounts') => 'account'])
        ->middleware([
            'edit' => 'account.active',
            'update' => 'account.active',
            'destroy' => 'account.active',
        ]);

    Route::patch(route_path('accounts.balance'), [AccountBalanceController::class, 'update'])
        ->middleware('account.active')
        ->name('accounts.balance.update');
});

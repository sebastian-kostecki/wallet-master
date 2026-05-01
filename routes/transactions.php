<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('transactions', TransactionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});

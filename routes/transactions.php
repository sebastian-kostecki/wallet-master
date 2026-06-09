<?php

use App\Http\Controllers\Transactions\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource(route_path('transactions'), TransactionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('transactions')
        ->parameters([route_path('transactions') => 'transaction']);
});

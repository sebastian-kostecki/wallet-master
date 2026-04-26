<?php

use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
});

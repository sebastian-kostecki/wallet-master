<?php

use App\Http\Controllers\Imports\ImportController;
use App\Http\Controllers\Imports\ImportFailedRowController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');

    Route::post('import-failed-rows/dismiss-all', [ImportFailedRowController::class, 'dismissAll'])
        ->name('import-failed-rows.dismiss-all');
    Route::post('import-failed-rows/{importFailedRow}/dismiss', [ImportFailedRowController::class, 'dismiss'])
        ->name('import-failed-rows.dismiss');

    Route::middleware('throttle:imports')->group(function () {
        Route::post('imports/upload', [ImportController::class, 'upload'])->name('imports.upload');
        Route::post('imports/{import}/commit', [ImportController::class, 'commit'])->name('imports.commit');
    });
});

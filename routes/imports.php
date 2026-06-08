<?php

use App\Http\Controllers\Imports\ImportController;
use App\Http\Controllers\Imports\ImportFailedRowController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get(route_path('imports'), [ImportController::class, 'index'])->name('imports.index');
    Route::get(route_path('imports').'/{import}', [ImportController::class, 'show'])->name('imports.show');

    Route::post(route_path('imports.failed_rows.dismiss_all'), [ImportFailedRowController::class, 'dismissAll'])
        ->name('import-failed-rows.dismiss-all');
    Route::post(route_path('imports.failed_rows.dismiss'), [ImportFailedRowController::class, 'dismiss'])
        ->name('import-failed-rows.dismiss');

    Route::middleware('throttle:imports')->group(function () {
        Route::post(route_path('imports.upload'), [ImportController::class, 'upload'])->name('imports.upload');
        Route::post(route_path('imports.commit'), [ImportController::class, 'commit'])->name('imports.commit');
    });
});

<?php

use App\Http\Controllers\Imports\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/upload', [ImportController::class, 'upload'])->name('imports.upload');
    Route::post('imports/{import}/commit', [ImportController::class, 'commit'])->name('imports.commit');
});

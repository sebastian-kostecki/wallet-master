<?php

use App\Http\Controllers\ImportCommitController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/upload', [ImportUploadController::class, 'store'])->name('imports.upload');
    Route::post('imports/{import}/commit', [ImportCommitController::class, 'store'])->name('imports.commit');
});

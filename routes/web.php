<?php

use App\Http\Controllers\Transaction\TransactionImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('imports', [TransactionImportController::class, 'index'])->name('imports.index');
    Route::get('imports/{import}', [TransactionImportController::class, 'show'])->name('imports.show');
    Route::post('imports/upload', [TransactionImportController::class, 'upload'])->name('imports.upload');
    Route::post('imports/{import}/commit', [TransactionImportController::class, 'commit'])->name('imports.commit');
});

require __DIR__.'/accounts.php';
require __DIR__.'/transactions.php';
require __DIR__.'/transfers.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

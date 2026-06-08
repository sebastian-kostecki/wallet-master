<?php

use App\Http\Controllers\Transfers\TransferCandidateController;
use App\Http\Controllers\Transfers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get(route_path('transfers.create'), [TransferController::class, 'create'])->name('transfers.create');
    Route::post(route_path('transfers'), [TransferController::class, 'store'])->name('transfers.store');
    Route::post(route_path('transfers.candidates.confirm'), [TransferCandidateController::class, 'confirm'])
        ->name('transfers.candidates.confirm');
    Route::post(route_path('transfers.candidates.reject'), [TransferCandidateController::class, 'reject'])
        ->name('transfers.candidates.reject');
    Route::post(route_path('transfers.unlink'), [TransferController::class, 'unlink'])->name('transfers.unlink');
});

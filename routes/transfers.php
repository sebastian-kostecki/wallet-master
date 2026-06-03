<?php

use App\Http\Controllers\Transfers\TransferCandidateController;
use App\Http\Controllers\Transfers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::post('transfers/candidates/{transaction}/confirm', [TransferCandidateController::class, 'confirm'])
        ->name('transfers.candidates.confirm');
    Route::post('transfers/candidates/{transaction}/reject', [TransferCandidateController::class, 'reject'])
        ->name('transfers.candidates.reject');
    Route::post('transfers/{transferId}/unlink', [TransferController::class, 'unlink'])->name('transfers.unlink');
});

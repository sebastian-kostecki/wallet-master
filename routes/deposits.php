<?php

use App\Http\Controllers\Deposits\DepositAssignmentController;
use App\Http\Controllers\Deposits\DepositController;
use App\Models\Deposit;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('deposit', fn (string $value) => Deposit::query()->findOrFail($value));

    Route::get(route_path('deposits').'/create', [DepositController::class, 'create'])->name('deposits.create');
    Route::post(route_path('deposits'), [DepositController::class, 'store'])->name('deposits.store');
    Route::get(route_path('deposits').'/{deposit}/edit', [DepositController::class, 'edit'])->name('deposits.edit');
    Route::patch(route_path('deposits').'/{deposit}', [DepositController::class, 'update'])->name('deposits.update');
    Route::post(route_path('deposits.close'), [DepositController::class, 'close'])->name('deposits.close');
    Route::post(route_path('deposits.assign'), [DepositAssignmentController::class, 'store'])->name('deposits.assign');
});

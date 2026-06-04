<?php

use App\Http\Controllers\Budgets\BudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('budget/monthly', [BudgetController::class, 'monthly'])->name('budget.monthly');
    Route::get('budget/yearly', [BudgetController::class, 'yearly'])->name('budget.yearly');
});

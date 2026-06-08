<?php

use App\Http\Controllers\Budgets\BudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get(route_path('budget.monthly'), [BudgetController::class, 'monthly'])->name('budget.monthly');
    Route::get(route_path('budget.yearly'), [BudgetController::class, 'yearly'])->name('budget.yearly');
});

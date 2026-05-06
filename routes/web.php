<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/accounts.php';
require __DIR__.'/imports.php';
require __DIR__.'/transactions.php';
require __DIR__.'/transfers.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

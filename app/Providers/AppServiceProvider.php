<?php

namespace App\Providers;

use App\Events\TransferCreated;
use App\Events\TransferFailedValidation;
use App\Listeners\LogTransferCreated;
use App\Listeners\LogTransferFailedValidation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TransferCreated::class, LogTransferCreated::class);
        Event::listen(TransferFailedValidation::class, LogTransferFailedValidation::class);
    }
}

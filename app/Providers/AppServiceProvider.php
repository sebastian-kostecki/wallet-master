<?php

namespace App\Providers;

use App\Events\Imports\ImportEnrichmentTypesenseHit;
use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use App\Events\TransferCreated;
use App\Events\TransferFailedValidation;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Integrations\DescriptionMemory\NullDescriptionMemoryRepository;
use App\Integrations\DescriptionMemory\TypesenseDescriptionMemoryRepository;
use App\Integrations\Typesense\TypesenseClient;
use App\Listeners\LogImportEnrichmentTypesenseHit;
use App\Listeners\LogImportEnrichmentTypesenseMiss;
use App\Listeners\LogTransferCreated;
use App\Listeners\LogTransferFailedValidation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TypesenseClient::class, function () {
            return new TypesenseClient(
                protocol: (string) config('services.typesense.protocol'),
                host: (string) config('services.typesense.host'),
                port: (int) config('services.typesense.port'),
                apiKey: (string) config('services.typesense.api_key'),
                timeoutMs: (int) config('services.typesense.timeout_ms'),
            );
        });

        $this->app->bind(DescriptionMemoryRepository::class, function () {
            if (! (bool) config('services.typesense.enabled')) {
                return new NullDescriptionMemoryRepository;
            }

            $apiKey = (string) config('services.typesense.api_key');
            if ($apiKey === '') {
                return new NullDescriptionMemoryRepository;
            }

            return new TypesenseDescriptionMemoryRepository(
                client: $this->app->make(TypesenseClient::class),
                minTextMatch: (int) config('services.typesense.min_text_match'),
                numTypos: (int) config('services.typesense.fuzzy_num_typos'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->isProduction()) {
            Model::shouldBeStrict();
        }

        RateLimiter::for('imports', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        Event::listen(TransferCreated::class, LogTransferCreated::class);
        Event::listen(TransferFailedValidation::class, LogTransferFailedValidation::class);
        Event::listen(ImportEnrichmentTypesenseHit::class, LogImportEnrichmentTypesenseHit::class);
        Event::listen(ImportEnrichmentTypesenseMiss::class, LogImportEnrichmentTypesenseMiss::class);
    }
}

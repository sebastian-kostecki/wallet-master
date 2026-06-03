<?php

declare(strict_types=1);

namespace App\Telemetry;

use Illuminate\Support\Facades\Log;

final class Event
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $name, array $payload = [], ?int $userId = null): void
    {
        Log::channel('telemetry')->info($name, array_merge([
            'event' => $name,
            'recorded_at' => now()->toIso8601String(),
            'user_id' => $userId ?? auth()->id(),
        ], $payload));
    }
}

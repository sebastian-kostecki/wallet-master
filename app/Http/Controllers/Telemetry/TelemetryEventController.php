<?php

declare(strict_types=1);

namespace App\Http\Controllers\Telemetry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Telemetry\StoreTelemetryEventRequest;
use App\Telemetry\Event;
use Illuminate\Http\Response;

final class TelemetryEventController extends Controller
{
    public function store(StoreTelemetryEventRequest $request): Response
    {
        Event::record(
            $request->validated('event'),
            $request->validated('payload', []),
            $request->user()->id,
        );

        return response()->noContent();
    }
}

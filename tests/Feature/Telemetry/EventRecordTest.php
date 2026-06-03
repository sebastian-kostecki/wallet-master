<?php

use App\Telemetry\Event;
use Illuminate\Support\Facades\Log;

test('event record writes to telemetry channel with envelope', function () {
    Log::fake();

    Event::record('transaction_created', ['transaction_id' => 42], userId: 7);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) {
        return $message === 'transaction_created'
            && $context['event'] === 'transaction_created'
            && $context['user_id'] === 7
            && $context['transaction_id'] === 42
            && isset($context['recorded_at']);
    });
});

<?php

use App\Telemetry\Event;

test('event record writes to telemetry channel with envelope', function () {
    $logged = captureTelemetryLogs(function (): void {
        Event::record('transaction_created', ['transaction_id' => 42], userId: 7);
    });

    expect($logged)->toHaveCount(1);
    expect($logged[0]['message'])->toBe('transaction_created');
    expect($logged[0]['context']['event'])->toBe('transaction_created');
    expect($logged[0]['context']['user_id'])->toBe(7);
    expect($logged[0]['context']['transaction_id'])->toBe(42);
    expect($logged[0]['context'])->toHaveKey('recorded_at');
});

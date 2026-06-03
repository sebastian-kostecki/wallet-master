<?php

use App\Models\User;

test('guest cannot post telemetry event', function () {
    $this->postJson(route('telemetry.store'), [
        'event' => 'transactions_filtered',
        'payload' => ['account_id' => 1],
    ])->assertUnauthorized();
});

test('authenticated user can post allowlisted client event', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('telemetry.store'), [
            'event' => 'transactions_filtered',
            'payload' => ['account_id' => 1],
        ])
        ->assertNoContent();
});

test('rejects unknown client event name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('telemetry.store'), [
            'event' => 'evil_event',
            'payload' => [],
        ])
        ->assertUnprocessable();
});

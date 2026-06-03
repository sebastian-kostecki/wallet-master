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

test('telemetry event is rate limited to sixty requests per minute', function () {
    $user = User::factory()->create();

    $payload = [
        'event' => 'transactions_filtered',
        'payload' => ['account_id' => 1],
    ];

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($user)
            ->postJson(route('telemetry.store'), $payload)
            ->assertNoContent();
    }

    $this->actingAs($user)
        ->postJson(route('telemetry.store'), $payload)
        ->assertStatus(429);
});

<?php

use App\Models\User;

test('goal with target requires planning mode and monthly contribution', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'target_amount' => '5000',
        'planning_mode' => 'monthly',
    ])->assertSessionHasErrors('monthly_contribution');
});

test('goal rejects both monthly contribution and target date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'target_amount' => '5000',
        'planning_mode' => 'monthly',
        'monthly_contribution' => '200',
        'target_date' => '2026-12-31',
    ])->assertSessionHasErrors('target_date');
});

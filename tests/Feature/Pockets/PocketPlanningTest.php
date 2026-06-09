<?php

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('pocket with target requires planning mode and monthly contribution', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'target_amount' => '5000',
        'planning_mode' => 'monthly',
    ])->assertSessionHasErrors('monthly_contribution');
});

test('pocket rejects both monthly contribution and target date', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'target_amount' => '5000',
        'planning_mode' => 'monthly',
        'monthly_contribution' => '200',
        'target_date' => '2026-12-31',
    ])->assertSessionHasErrors('target_date');
});

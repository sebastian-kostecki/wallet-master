<?php

use App\Enums\PocketPlanningMode;
use App\Models\Currency;
use App\Models\Pocket;
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

test('pocket without target can store optional monthly contribution', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Bufor',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'monthly_contribution' => '200',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Bufor')->first();

    expect($pocket)->not->toBeNull()
        ->and($pocket->target_amount)->toBeNull()
        ->and($pocket->planning_mode)->toBeNull()
        ->and((string) $pocket->monthly_contribution)->toBe('200.00');
});

test('pocket without target can be stored without monthly contribution', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Luźne',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Luźne')->first();

    expect($pocket)->not->toBeNull()
        ->and($pocket->monthly_contribution)->toBeNull();
});

test('clearing target keeps monthly contribution on update', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'target_amount' => '5000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '250.00',
    ]);

    $this->actingAs($user)->patch(route('pockets.update', $pocket), [
        'target_amount' => '',
        'monthly_contribution' => '250',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $pocket->refresh();

    expect($pocket->target_amount)->toBeNull()
        ->and($pocket->planning_mode)->toBeNull()
        ->and((string) $pocket->monthly_contribution)->toBe('250.00');
});

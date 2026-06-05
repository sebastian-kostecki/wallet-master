<?php

use App\Models\Currency;
use App\Models\Goal;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create goal with currency PLN', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertRedirect();

    $goal = Goal::query()->where('user_id', $user->id)->where('name', 'Wakacje')->first();

    expect($goal)->not->toBeNull();
    expect((int) $goal->currency_id)->toBe($plnId);
});

test('goal resource includes nested currency', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ]);

    $response = $this->actingAs($user)->get(route('goals.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('goals', 1)
        ->where('goals.0.currency.code', 'PLN')
        ->where('goals.0.currency.symbol', 'zł')
    );
});

test('create goal requires currency_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
    ])->assertSessionHasErrors('currency_id');
});

test('update goal does not accept currency_id', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $goal = Goal::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);

    $this->actingAs($user)->patch(route('goals.update', $goal), [
        'name' => 'Renamed',
        'currency_id' => $plnId,
    ])->assertSessionHasErrors('currency_id');

    expect((int) $goal->fresh()->currency_id)->toBe($plnId);
});

<?php

use App\Models\Currency;
use App\Models\Pocket;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create pocket with currency PLN', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertRedirect();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Wakacje')->first();

    expect($pocket)->not->toBeNull();
    expect((int) $pocket->currency_id)->toBe($plnId);
});

test('pocket resource includes nested currency', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ]);

    $response = $this->actingAs($user)->get(route('pockets.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('pockets', 1)
        ->where('pockets.0.currency.code', 'PLN')
        ->where('pockets.0.currency.symbol', 'zł')
    );
});

test('create pocket requires currency_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
    ])->assertSessionHasErrors('currency_id');
});

test('update pocket does not accept currency_id', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);

    $this->actingAs($user)->patch(route('pockets.update', $pocket), [
        'name' => 'Renamed',
        'currency_id' => $plnId,
    ])->assertSessionHasErrors('currency_id');

    expect((int) $pocket->fresh()->currency_id)->toBe($plnId);
});

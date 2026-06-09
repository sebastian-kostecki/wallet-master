<?php

use App\Models\Pocket;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(fn () => $this->seed(CurrencySeeder::class));

test('reorder updates pocket sort order', function () {
    $user = User::factory()->create();

    $pocketA = Pocket::factory()->create(['user_id' => $user->id, 'sort_order' => 10]);
    $pocketB = Pocket::factory()->create(['user_id' => $user->id, 'sort_order' => 20]);

    $this->actingAs($user)->patch(route('pockets.reorder'), [
        'ids' => [$pocketB->id, $pocketA->id],
    ])->assertRedirect();

    expect($pocketA->fresh()->sort_order)->toBe(20);
    expect($pocketB->fresh()->sort_order)->toBe(10);
});

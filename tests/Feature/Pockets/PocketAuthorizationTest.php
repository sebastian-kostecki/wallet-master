<?php

use App\Models\Pocket;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(fn () => $this->seed(CurrencySeeder::class));

test('user cannot update another users pocket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->patch(route('pockets.update', $pocket), ['name' => 'Hacked'])
        ->assertForbidden();
});

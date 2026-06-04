<?php

use App\Models\Goal;
use App\Models\User;

test('reorder updates goal sort order', function () {
    $user = User::factory()->create();

    $goalA = Goal::factory()->create(['user_id' => $user->id, 'sort_order' => 10]);
    $goalB = Goal::factory()->create(['user_id' => $user->id, 'sort_order' => 20]);

    $this->actingAs($user)->patch(route('goals.reorder'), [
        'ids' => [$goalB->id, $goalA->id],
    ])->assertRedirect();

    expect($goalA->fresh()->sort_order)->toBe(20);
    expect($goalB->fresh()->sort_order)->toBe(10);
});

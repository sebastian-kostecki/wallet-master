<?php

use App\Models\Goal;
use App\Models\User;

test('user cannot update another users goal', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->patch(route('goals.update', $goal), ['name' => 'Hacked'])
        ->assertForbidden();
});

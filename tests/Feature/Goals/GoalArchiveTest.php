<?php

use App\Enums\GoalPlanningMode;
use App\Models\Goal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('archived goal is hidden from default index and monthly budget', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $active = Goal::factory()->create(['user_id' => $user->id, 'name' => 'Active goal']);
    $archived = Goal::factory()->create([
        'user_id' => $user->id,
        'name' => 'Archived goal',
        'target_amount' => '1000.00',
        'planning_mode' => GoalPlanningMode::Monthly,
        'monthly_contribution' => '100.00',
        'is_archived' => true,
    ]);

    $this->actingAs($user)->get(route('goals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goals', fn ($goals) => collect($goals)->pluck('name')->contains('Active goal'))
            ->where('goals', fn ($goals) => collect($goals)->pluck('name')->doesntContain('Archived goal'))
        );

    $this->actingAs($user)->patch(route('goals.update', $archived), [
        'is_archived' => false,
    ])->assertRedirect();

    $this->actingAs($user)->get(route('goals.index', ['filter' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goals', fn ($goals) => collect($goals)->pluck('name')->contains('Archived goal'))
        );

    $this->actingAs($user)->get('/budget/monthly?year=2026&month=3')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goal_rows', fn ($rows) => collect($rows)->pluck('name')->contains('Archived goal'))
        );

    $this->actingAs($user)->patch(route('goals.update', $active), [
        'is_archived' => true,
    ])->assertRedirect();

    $this->actingAs($user)->get('/budget/monthly?year=2026&month=3')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goal_rows', fn ($rows) => collect($rows)->pluck('name')->doesntContain('Active goal'))
        );
});

<?php

use App\Models\Goal;
use App\Models\GoalAnnualEstimate;
use App\Models\GoalMonthlyEstimate;
use App\Models\User;

test('goal annual estimate upsert', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->patch(route('goals.estimates.annual', $goal), [
        'year' => 2026,
        'amount' => '2400',
    ])->assertRedirect();

    expect(GoalAnnualEstimate::where('goal_id', $goal->id)->where('year', 2026)->value('amount'))
        ->toBe('2400.00');
});

test('user can save monthly estimate override', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('goals.estimates.monthly', $goal), [
        'year' => 2026,
        'month' => 3,
        'amount' => 1500,
    ]);

    $response->assertSessionHasNoErrors();

    $estimate = GoalMonthlyEstimate::query()
        ->where('goal_id', $goal->id)
        ->where('year', 2026)
        ->where('month', 3)
        ->first();

    expect($estimate)->not->toBeNull();
    expect((string) $estimate->amount)->toBe('1500.00');
});

test('estimate amount must be zero or positive', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('goals.estimates.annual', $goal), [
            'year' => 2026,
            'amount' => -1,
        ])
        ->assertSessionHasErrors('amount');
});

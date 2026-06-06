<?php

use App\Enums\PocketPlanningMode;
use App\Models\Pocket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('archived pocket is hidden from default index and monthly budget', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $active = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Active pocket']);
    $archived = Pocket::factory()->create([
        'user_id' => $user->id,
        'name' => 'Archived pocket',
        'target_amount' => '1000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '100.00',
        'is_archived' => true,
    ]);

    $this->actingAs($user)->get(route('pockets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pockets', fn ($pockets) => collect($pockets)->pluck('name')->contains('Active pocket'))
            ->where('pockets', fn ($pockets) => collect($pockets)->pluck('name')->doesntContain('Archived pocket'))
        );

    $this->actingAs($user)->patch(route('pockets.update', $archived), [
        'is_archived' => false,
    ])->assertRedirect();

    $this->actingAs($user)->get(route('pockets.index', ['filter' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pockets', fn ($pockets) => collect($pockets)->pluck('name')->contains('Archived pocket'))
        );

    $this->actingAs($user)->get('/budget/monthly?year=2026&month=3')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pocket_rows', fn ($rows) => collect($rows)->pluck('name')->contains('Archived pocket'))
        );

    $this->actingAs($user)->patch(route('pockets.update', $active), [
        'is_archived' => true,
    ])->assertRedirect();

    $this->actingAs($user)->get('/budget/monthly?year=2026&month=3')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pocket_rows', fn ($rows) => collect($rows)->pluck('name')->doesntContain('Active pocket'))
        );
});

test('archive toggle only updates is_archived without clearing planning fields', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'name' => 'Vacation fund',
        'target_amount' => '5000.00',
        'planning_mode' => PocketPlanningMode::Monthly,
        'monthly_contribution' => '250.00',
        'is_archived' => false,
    ]);

    $this->actingAs($user)
        ->from(route('pockets.index', ['filter' => 'active']))
        ->patch(route('pockets.update', $pocket), [
            'is_archived' => true,
        ])
        ->assertRedirect(route('pockets.index', ['filter' => 'active']));

    $pocket->refresh();

    expect($pocket->is_archived)->toBeTrue()
        ->and($pocket->target_amount)->toBe('5000.00')
        ->and($pocket->planning_mode)->toBe(PocketPlanningMode::Monthly)
        ->and($pocket->monthly_contribution)->toBe('250.00');
});

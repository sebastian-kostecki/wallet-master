<?php

use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\User;

test('user can save annual estimate for category', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Jedzenie')
        ->firstOrFail();

    $response = $this->actingAs($user)->patch("/categories/{$food->id}/estimates/annual", [
        'year' => 2026,
        'amount' => 12000,
    ]);

    $response->assertSessionHasNoErrors();

    $estimate = CategoryAnnualEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->first();

    expect($estimate)->not->toBeNull();
    expect((string) $estimate->amount)->toBe('12000.00');
});

test('user can save monthly estimate override', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Jedzenie')
        ->firstOrFail();

    $response = $this->actingAs($user)->patch("/categories/{$food->id}/estimates/monthly", [
        'year' => 2026,
        'month' => 3,
        'amount' => 1500,
    ]);

    $response->assertSessionHasNoErrors();

    $estimate = CategoryMonthlyEstimate::query()
        ->where('category_id', $food->id)
        ->where('year', 2026)
        ->where('month', 3)
        ->first();

    expect($estimate)->not->toBeNull();
    expect((string) $estimate->amount)->toBe('1500.00');
});

test('estimate amount must be zero or positive', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $food = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Jedzenie')
        ->firstOrFail();

    $this->actingAs($user)
        ->patch("/categories/{$food->id}/estimates/annual", [
            'year' => 2026,
            'amount' => -1,
        ])
        ->assertSessionHasErrors('amount');
});

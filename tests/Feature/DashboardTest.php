<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard', absolute: false));
    $response->assertRedirect(route('login', absolute: false));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', absolute: false));
    $response->assertStatus(200);
});

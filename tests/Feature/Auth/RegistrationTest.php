<?php

test('registration screen can be rendered', function () {
    config(['auth.registration.enabled' => true]);

    $response = $this->get(route('register', absolute: false));

    $response->assertStatus(200);
});

test('new users can register', function () {
    config(['auth.registration.enabled' => true]);

    $response = $this->post(route('register', absolute: false), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration screen is not available when registration is disabled', function () {
    config(['auth.registration.enabled' => false]);

    $response = $this->get(route('register', absolute: false));

    $response->assertNotFound();
});

test('registration requests are rejected when registration is disabled', function () {
    config(['auth.registration.enabled' => false]);

    $response = $this->post(route('register', absolute: false), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
});

test('login page exposes canRegister false when registration is disabled', function () {
    config(['auth.registration.enabled' => false]);

    $response = $this->get(route('login', absolute: false));

    $response->assertOk()->assertInertia(fn ($page) => $page->where('canRegister', false));
});

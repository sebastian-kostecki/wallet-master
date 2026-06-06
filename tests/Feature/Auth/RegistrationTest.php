<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
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

    $response = $this->get('/register');

    $response->assertNotFound();
});

test('registration requests are rejected when registration is disabled', function () {
    config(['auth.registration.enabled' => false]);

    $response = $this->post('/register', [
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

    $response = $this->get('/login');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('canRegister', false));
});

<?php

declare(strict_types=1);

use App\Models\User;

test('canonical polish transaction index is reachable when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transakcje')->assertOk();
});

test('legacy english transaction index redirects to polish with query string', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions?sort=date&direction=desc')
        ->assertRedirect('/transakcje?sort=date&direction=desc');
});

test('login page renders at polish path', function () {
    $this->get('/logowanie')->assertOk();
});

test('legacy login path redirects to polish login', function () {
    $this->get('/login')->assertRedirect('/logowanie');
});

test('named route helper generates polish paths', function () {
    expect(route('transactions.index', absolute: false))->toBe('/transakcje');
    expect(route('login', absolute: false))->toBe('/logowanie');
    expect(route('dashboard', absolute: false))->toBe('/panel');
});

test('legacy post login still authenticates', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

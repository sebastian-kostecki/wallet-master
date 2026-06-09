<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Http\Request;

test('web responses do not include security headers outside production', function () {
    $response = $this->get(route('login', absolute: false));

    $response->assertOk();
    $response->assertHeaderMissing('X-Frame-Options');
    $response->assertHeaderMissing('Content-Security-Policy');
});

test('security headers middleware adds expected headers', function () {
    $middleware = new SecurityHeaders;
    $request = Request::create(route('login', absolute: false));

    $response = $middleware->handle($request, fn (Request $request) => response('ok'));

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("script-src 'self' 'nonce-");
    expect($csp)->toContain("connect-src 'self'");
});

test('ziggy routes are embedded as non-executable json', function () {
    $response = $this->get(route('login', absolute: false));

    $response->assertOk();
    $response->assertSee('id="ziggy-routes-json"', false);
    $response->assertSee('type="application/json"', false);
    expect($response->getContent())->not->toMatch('/<script type="text\/javascript">const Ziggy=/');
});

test('ziggy json embed includes dashboard route', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', absolute: false));

    $response->assertOk();

    preg_match('/<script id="ziggy-routes-json" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
    expect($matches)->toHaveCount(2);

    $ziggy = json_decode($matches[1], true);

    expect($ziggy)->toHaveKey('routes');
    expect($ziggy['routes'])->toHaveKey('dashboard');
});

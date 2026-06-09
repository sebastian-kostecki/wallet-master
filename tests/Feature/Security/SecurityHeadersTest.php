<?php

declare(strict_types=1);

test('web responses include security headers', function () {
    $response = $this->get(route('login', absolute: false));

    $response->assertOk();
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
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

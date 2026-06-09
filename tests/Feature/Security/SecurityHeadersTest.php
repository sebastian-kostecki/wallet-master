<?php

declare(strict_types=1);

test('web responses include security headers', function () {
    $response = $this->get(route('login', absolute: false));

    $response->assertOk();
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
});

<?php

use App\Http\Middleware\ConfigureInertiaSsr;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('disables inertia ssr for configured path patterns', function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.except_paths' => ['settings/*', 'admin/*'],
    ]);

    $request = Request::create('/settings/profile');

    (new ConfigureInertiaSsr)->handle($request, fn () => new Response('ok'));

    expect(config('inertia.ssr.enabled'))->toBeFalse();
});

it('keeps inertia ssr enabled for non-excluded paths', function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.except_paths' => ['settings/*'],
    ]);

    $request = Request::create('/accounts');

    (new ConfigureInertiaSsr)->handle($request, fn () => new Response('ok'));

    expect(config('inertia.ssr.enabled'))->toBeTrue();
});

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.registration.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}

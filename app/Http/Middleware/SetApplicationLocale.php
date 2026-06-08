<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale((string) config('routes.default_locale', 'pl'));

        return $next($request);
    }
}

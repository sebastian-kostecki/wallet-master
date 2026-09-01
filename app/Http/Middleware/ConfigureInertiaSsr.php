<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureInertiaSsr
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (config('inertia.ssr.except_paths', []) as $path) {
            if ($request->is($path)) {
                config(['inertia.ssr.enabled' => false]);

                break;
            }
        }

        return $next($request);
    }
}

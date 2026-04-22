<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->route('account');

        if ($account instanceof Account && $account->trashed()) {
            abort(403);
        }

        return $next($request);
    }
}

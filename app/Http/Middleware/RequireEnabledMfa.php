<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEnabledMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            abort(403, 'Admin is not authenticated.');
        }

        if (! $admin->hasConfirmedMfa()) {
            abort(403, 'MFA must be enabled before managing mail settings.');
        }

        return $next($request);
    }
}
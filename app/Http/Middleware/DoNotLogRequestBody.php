<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DoNotLogRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('do_not_log_request_body', true);

        return $next($request);
    }
}
<?php

namespace App\Http\Middleware;

use App\Services\AdminMfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVendorMfa
{
    public function __construct(protected AdminMfaService $mfaService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            abort(403, 'Vendor is not authenticated.');
        }

        if (! $admin->isVendor() || $this->mfaService->isEnabled($admin)) {
            return $next($request);
        }

        abort(403, 'MFA must be enabled before accessing vendor-sensitive actions.');
    }
}

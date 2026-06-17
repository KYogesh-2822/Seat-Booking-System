<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMailSettingsMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('mail_settings_mfa_confirmed_at');

        if (! $confirmedAt || Carbon::parse($confirmedAt)->lt(now()->subMinutes(10))) {
            return redirect()
                ->route('admin.mail-settings.mfa.create')
                ->with('status', 'Confirm MFA before changing mail credentials.');
        }

        return $next($request);
    }
}
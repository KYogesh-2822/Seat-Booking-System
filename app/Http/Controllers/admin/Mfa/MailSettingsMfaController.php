<?php

namespace App\Http\Controllers\admin\Mfa;

use App\Http\Controllers\Controller;
use App\Services\AdminMfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class MailSettingsMfaController extends Controller
{
    public function __construct(protected AdminMfaService $mfaService)
    {
    }

    public function create(): View
    {
        return view('admin.mail-settings.mfa-confirm');
    }

    public function store(
        Request $request,
        TwoFactorAuthenticationProvider $provider
    ): RedirectResponse {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $admin = $request->user('admin');

        if (! $admin || ! $this->mfaService->isEnabled($admin)) {
            abort(403, 'MFA is not enabled.');
        }

        $valid = $this->mfaService->verifyAdminCode($admin, $data['code'], $provider);

        if (! $valid) {
            return back()->withErrors([
                'code' => 'Invalid MFA code.',
            ]);
        }

        $request->session()->put(
            'mail_settings_mfa_confirmed_at',
            now()->toISOString()
        );

        return redirect()
            ->intended(route('admin.mail-settings.edit', [
                'environment' => 'live',
            ]))
            ->with('status', 'MFA confirmed.');
    }
}
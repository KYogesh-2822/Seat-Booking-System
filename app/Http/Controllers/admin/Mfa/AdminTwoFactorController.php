<?php

namespace App\Http\Controllers\admin\Mfa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class AdminTwoFactorController extends Controller
{
    public function showSetup(Request $request): View
    {
        $admin = $request->user('admin');

        if ($admin && filled($admin->two_factor_secret) && filled($admin->two_factor_confirmed_at)) {
            return view('admin.mfa.setup')->with(['enabled' => true, 'admin' => $admin]);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Store the temporary secret in session until confirmed
        $request->session()->put('admin_2fa_secret', $secret);

        $company = config('app.name', 'App');
        $otpAuthUrl = $google2fa->getQRCodeUrl($company, $admin->email, $secret);

        // Use qrserver.com as a stable public QR generator (fallback to avoid Google Chart 404s)
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpAuthUrl);

        return view('admin.mfa.setup')->with([
            'enabled' => false,
            'qrImageUrl' => $qrImageUrl,
            'secret' => $secret,
            'admin' => $admin,
        ]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $admin = $request->user('admin');
        $secret = $request->session()->get('admin_2fa_secret');

        if (! $secret) {
            return back()->withErrors(['code' => 'Setup expired. Please reload the setup page.']);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $data['code']);

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid MFA code.']);
        }

        $admin->two_factor_secret = encrypt($secret);
        $admin->two_factor_confirmed_at = now();
        $admin->save();

        $request->session()->forget('admin_2fa_secret');

        return redirect()->route('admin.mfa.setup')->with('status', 'Two-factor authentication enabled.');
    }

    public function disable(Request $request)
    {
        $admin = $request->user('admin');

        $admin->two_factor_secret = null;
        $admin->two_factor_confirmed_at = null;
        $admin->save();

        return redirect()->route('admin.mfa.setup')->with('status', 'Two-factor authentication disabled.');
    }
}

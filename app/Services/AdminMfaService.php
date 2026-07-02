<?php

namespace App\Services;

use App\Models\Admin;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

class AdminMfaService
{
    public function __construct(protected Google2FA $google2fa)
    {
    }

    public function isEnabled(Admin $admin): bool
    {
        return $admin->hasConfirmedMfa();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function verifySetupCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    public function verifyAdminCode(Admin $admin, string $code, TwoFactorAuthenticationProvider $provider): bool
    {
        return $provider->verify(decrypt($admin->two_factor_secret), $code);
    }
}

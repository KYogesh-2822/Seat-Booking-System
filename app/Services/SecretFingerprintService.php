<?php

namespace App\Services;

class SecretFingerprintService
{
    public function make(?string $secret): ?string
    {
        if (blank($secret)) {
            return null;
        }

        return substr(
            hash_hmac('sha256', $secret, config('app.key')),
            0,
            16
        );
    }
}
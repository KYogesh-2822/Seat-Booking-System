<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        Request $request,
        string $action,
        ?Model $subject = null,
        ?string $environment = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        SecurityAuditLog::query()->create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'environment' => $environment,
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $secretKeys = [
            'password',
            'mail_password',
            'secret',
            'token',
            'api_key',
            'private_key',
            'authorization',
        ];

        foreach ($values as $key => $value) {
            foreach ($secretKeys as $secretKey) {
                if (str_contains(strtolower((string) $key), $secretKey)) {
                    $values[$key] = '[REDACTED]';
                }
            }
        }

        return $values;
    }
}
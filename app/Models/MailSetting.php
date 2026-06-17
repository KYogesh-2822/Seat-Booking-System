<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'environment',
        'status',
        'mail_mailer',
        'mail_scheme',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'secret_fingerprint',
        'mail_from_address',
        'mail_from_name',
        'validated_at',
        'validated_by',
        'activated_at',
        'activated_by',
        'created_by',
    ];

    protected $hidden = [
        'mail_password',
    ];

    protected function casts(): array
    {
        return [
            'mail_port' => 'integer',
            'mail_password' => 'encrypted',
            'validated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function redactedForAudit(): array
    {
        return [
            'id' => $this->id,
            'environment' => $this->environment,
            'status' => $this->status,
            'mail_mailer' => $this->mail_mailer,
            'mail_scheme' => $this->mail_scheme,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_username' => $this->mail_username,
            'mail_password' => $this->mail_password ? '[REDACTED]' : null,
            'secret_fingerprint' => $this->secret_fingerprint,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
            'validated_at' => $this->validated_at?->toISOString(),
            'activated_at' => $this->activated_at?->toISOString(),
        ];
    }
}
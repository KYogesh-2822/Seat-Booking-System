<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Mail;

class MailConfigService
{
    public function applyActive(string $environment = 'live'): void
    {
        $setting = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        if ($setting) {
            $this->apply($setting);
        }
    }

    public function apply(MailSetting $setting): void
    {
        if ($setting->mail_mailer === 'smtp') {
            config([
                'mail.default' => 'admin_smtp',

                'mail.mailers.admin_smtp' => [
                    'transport' => 'smtp',
                    'scheme' => $setting->mail_scheme ?: null,
                    'url' => null,
                    'host' => $setting->mail_host,
                    'port' => $setting->mail_port,
                    'username' => $setting->mail_username ?: null,
                    'password' => $setting->mail_password ?: null,
                    'timeout' => null,
                    'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
                ],

                'mail.from' => [
                    'address' => $setting->mail_from_address,
                    'name' => $setting->mail_from_name,
                ],
            ]);
        }

        if ($setting->mail_mailer === 'log') {
            config([
                'mail.default' => 'log',
                'mail.from' => [
                    'address' => $setting->mail_from_address,
                    'name' => $setting->mail_from_name,
                ],
            ]);
        }

        if ($setting->mail_mailer === 'array') {
            config([
                'mail.default' => 'array',
                'mail.from' => [
                    'address' => $setting->mail_from_address,
                    'name' => $setting->mail_from_name,
                ],
            ]);
        }

        app('mail.manager')->forgetMailers();
    }

    public function test(MailSetting $setting, string $to): void
    {
        $this->apply($setting);

        Mail::raw('This is a test email for pending mail settings.', function ($message) use ($to) {
            $message->to($to)
                ->subject('Laravel Mail Settings Test');
        });
    }
}
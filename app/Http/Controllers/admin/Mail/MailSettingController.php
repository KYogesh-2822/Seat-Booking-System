<?php

namespace App\Http\Controllers\admin\Mail;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use App\Notifications\MailSettingsChangedNotification;
use App\Services\AuditLogService;
use App\Services\MailConfigService;
use App\Services\SecretFingerprintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MailSettingController extends Controller
{
    public function edit(string $environment = 'live'): View
    {
        abort_unless(in_array($environment, ['test', 'live'], true), 404);

        $active = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        $pending = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $previous = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'previous')
            ->latest('activated_at')
            ->first();

        return view('admin.mail-settings.edit', compact(
            'environment',
            'active',
            'pending',
            'previous'
        ));
    }

    public function storePending(
        Request $request,
        string $environment,
        SecretFingerprintService $fingerprints,
        AuditLogService $audit
    ): RedirectResponse {
        abort_unless(in_array($environment, ['test', 'live'], true), 404);

        $data = $request->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'log', 'array'])],
            'mail_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'mail_host' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2000'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        $active = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        /*
         | If password is blank and there is an active version,
         | reuse the existing secret without sending it to frontend.
         */
        if (blank($data['mail_password'] ?? null) && $active) {
            $data['mail_password'] = $active->mail_password;
            $data['secret_fingerprint'] = $active->secret_fingerprint;
        } elseif (filled($data['mail_password'] ?? null)) {
            $data['secret_fingerprint'] = $fingerprints->make($data['mail_password']);
        } else {
            $data['secret_fingerprint'] = null;
        }

        $data['environment'] = $environment;
        $data['status'] = 'pending';
        $data['created_by'] = $request->user()->id;
        $data['validated_at'] = null;
        $data['validated_by'] = null;
        $data['activated_at'] = null;
        $data['activated_by'] = null;

        $setting = DB::transaction(function () use ($environment, $data) {
            MailSetting::query()
                ->where('environment', $environment)
                ->where('status', 'pending')
                ->update(['status' => 'revoked']);

            return MailSetting::query()->create($data);
        });

        $audit->record(
            request: $request,
            action: 'mail_settings.pending_saved',
            subject: $setting,
            environment: $environment,
            oldValues: $active?->redactedForAudit(),
            newValues: $setting->redactedForAudit()
        );

        $this->sendAlert($request, 'Pending mail settings saved', $setting);

        return back()->with('status', 'Pending mail settings saved. Send a test email before activation.');
    }

    public function validatePending(
        Request $request,
        string $environment,
        MailConfigService $mailConfig,
        AuditLogService $audit
    ): RedirectResponse {
        abort_unless(in_array($environment, ['test', 'live'], true), 404);

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $pending = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail();

        try {
            $mailConfig->test($pending, $data['test_email']);

            $pending->forceFill([
                'validated_at' => now(),
                'validated_by' => $request->user()->id,
            ])->save();

            $audit->record(
                request: $request,
                action: 'mail_settings.pending_validated',
                subject: $pending,
                environment: $environment,
                newValues: $pending->redactedForAudit()
            );

            return back()->with('status', 'Pending mail settings validated successfully.');
        } catch (Throwable $e) {
            $pending->forceFill([
                'status' => 'failed',
            ])->save();

            $audit->record(
                request: $request,
                action: 'mail_settings.validation_failed',
                subject: $pending,
                environment: $environment,
                newValues: [
                    'id' => $pending->id,
                    'environment' => $environment,
                    'status' => 'failed',
                    'error' => class_basename($e),
                ]
            );

            return back()->withErrors([
                'test_email' => 'Mail validation failed. Check host, port, username, password, scheme, and from address.',
            ]);
        }
    }

    public function activate(
        Request $request,
        string $environment,
        AuditLogService $audit
    ): RedirectResponse {
        abort_unless(in_array($environment, ['test', 'live'], true), 404);

        $pending = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'pending')
            ->whereNotNull('validated_at')
            ->latest('validated_at')
            ->firstOrFail();

        $activeBefore = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        DB::transaction(function () use ($environment, $pending, $request) {
            MailSetting::query()
                ->where('environment', $environment)
                ->where('status', 'active')
                ->update(['status' => 'previous']);

            $pending->forceFill([
                'status' => 'active',
                'activated_at' => now(),
                'activated_by' => $request->user()->id,
            ])->save();
        });

        $pending->refresh();

        $audit->record(
            request: $request,
            action: 'mail_settings.activated',
            subject: $pending,
            environment: $environment,
            oldValues: $activeBefore?->redactedForAudit(),
            newValues: $pending->redactedForAudit()
        );

        $this->sendAlert($request, 'Mail settings activated', $pending);

        return back()->with('status', 'Mail settings activated successfully.');
    }

    public function rollback(
        Request $request,
        string $environment,
        AuditLogService $audit
    ): RedirectResponse {
        abort_unless(in_array($environment, ['test', 'live'], true), 404);

        $current = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        $previous = MailSetting::query()
            ->where('environment', $environment)
            ->where('status', 'previous')
            ->latest('activated_at')
            ->firstOrFail();

        DB::transaction(function () use ($current, $previous, $request) {
            if ($current) {
                $current->forceFill([
                    'status' => 'previous',
                ])->save();
            }

            $previous->forceFill([
                'status' => 'active',
                'activated_at' => now(),
                'activated_by' => $request->user()->id,
            ])->save();
        });

        $previous->refresh();

        $audit->record(
            request: $request,
            action: 'mail_settings.rollback',
            subject: $previous,
            environment: $environment,
            oldValues: $current?->redactedForAudit(),
            newValues: $previous->redactedForAudit()
        );

        $this->sendAlert($request, 'Mail settings rolled back', $previous);

        return back()->with('status', 'Rolled back to previous working mail settings.');
    }

    private function sendAlert(Request $request, string $event, MailSetting $setting): void
    {
        foreach (config('security.alert_emails', []) as $email) {
            Notification::route('mail', $email)->notify(
                new MailSettingsChangedNotification(
                    event: $event,
                    setting: $setting,
                    actorEmail: $request->user()->email,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                )
            );
        }
    }
}
<?php

namespace App\Notifications;

use App\Models\MailSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MailSettingsChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $event,
        public MailSetting $setting,
        public string $actorEmail,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Security Alert: Mail Settings Changed')
            ->line('Mail credentials/settings were changed.')
            ->line('Event: ' . $this->event)
            ->line('Environment: ' . $this->setting->environment)
            ->line('Actor: ' . $this->actorEmail)
            ->line('IP: ' . ($this->ipAddress ?: 'unknown'))
            ->line('User Agent: ' . substr((string) $this->userAgent, 0, 200))
            ->line('Mailer: ' . $this->setting->mail_mailer)
            ->line('Host: ' . ($this->setting->mail_host ?: 'none'))
            ->line('Username: ' . ($this->setting->mail_username ?: 'none'))
            ->line('Secret Fingerprint: ' . ($this->setting->secret_fingerprint ?: 'none'))
            ->line('No secret value is included in this alert.');
    }
}
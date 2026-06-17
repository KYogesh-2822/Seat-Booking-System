<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Admin;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
                Gate::define('manage-mail-settings', function ($admin): bool {
                    return method_exists($admin, 'isOwnerOrAdmin')
                        && $admin->isOwnerOrAdmin();
                });
            

        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        try {
            if (Schema::hasTable('mail_settings')) {
                app(MailConfigService::class)->applyActive('live');
            }
        } catch (Throwable) {
            // Prevent boot failure during fresh install / migration / DB downtime.
        }
    }
}
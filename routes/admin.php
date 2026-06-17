<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\AdminAuthController;
use App\Http\Controllers\admin\StripeConnectController;
use App\Http\Controllers\admin\Mail\MailSettingController;
use App\Http\Controllers\admin\Mfa\MailSettingsMfaController;
use App\Http\Controllers\admin\Mfa\AdminTwoFactorController;
use App\Http\Controllers\Event\EventController;

/*
|--------------------------------------------------------------------------
| Admin & Vendor Routes
|--------------------------------------------------------------------------
|
| Routes here are for admins and vendors only.
| Loaded from bootstrap/app.php under the "web" middleware group.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Admin Auth Routes
    |--------------------------------------------------------------------------
    |
    | Final URLs:
    | GET  /admin/login
    | POST /admin/login
    |
    */

    Route::middleware('guest:admin')
        ->controller(AdminAuthController::class)
        ->group(function () {
            Route::get('/login', 'showLogin')->name('login');

            Route::post('/login', 'login')
                ->middleware('throttle:6,1')
                ->name('login.submit');
        });

    /*
    |--------------------------------------------------------------------------
    | Logged-in Admin / Vendor Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:admin', 'no-cache'])->group(function () {

        Route::post('/logout', [AdminAuthController::class, 'logout'])
            ->name('logout');

        // Admin 2FA setup routes
        Route::prefix('2fa')->name('mfa.')->controller(AdminTwoFactorController::class)->group(function () {
            Route::get('/setup', 'showSetup')->name('setup');
            Route::post('/confirm', 'confirm')->name('confirm');
            Route::post('/disable', 'disable')->name('disable');
        });

        /*
        |--------------------------------------------------------------------------
        | Secure Mail Settings Routes
        |--------------------------------------------------------------------------
        |
        | Final URLs:
        | GET  /admin/mail-settings
        | GET  /admin/mail-settings/live
        | GET  /admin/mail-settings/test
        | POST /admin/mail-settings/live/pending
        | POST /admin/mail-settings/live/validate
        | POST /admin/mail-settings/live/activate
        | POST /admin/mail-settings/live/rollback
        |
        */

        Route::prefix('mail-settings')
            ->name('mail-settings.')
            ->middleware([
                'auth:admin',
                'can:manage-mail-settings',
                'mfa.enabled',
                'no.body.logs',

                // Add this only after you create admin password-confirm routes.
                // 'password.confirm',
            ])
            ->group(function () {

                Route::get('/', function () {
                    return redirect()->route('admin.mail-settings.edit', [
                        'environment' => 'live',
                    ]);
                })->name('index');

                /*
                 | MFA confirmation routes.
                 | Do NOT add "mail.mfa" here, otherwise admin cannot reach
                 | the page needed to confirm MFA.
                 */
                Route::get('/mfa/confirm', [MailSettingsMfaController::class, 'create'])
                    ->name('mfa.create');

                Route::post('/mfa/confirm', [MailSettingsMfaController::class, 'store'])
                    ->middleware('throttle:6,1')
                    ->name('mfa.store');

                Route::get('/{environment}', [MailSettingController::class, 'edit'])
                    ->whereIn('environment', ['test', 'live'])
                    ->name('edit');

                Route::post('/{environment}/pending', [MailSettingController::class, 'storePending'])
                    ->whereIn('environment', ['test', 'live'])
                    ->middleware('mail.mfa')
                    ->name('pending.store');

                Route::post('/{environment}/validate', [MailSettingController::class, 'validatePending'])
                    ->whereIn('environment', ['test', 'live'])
                    ->middleware('mail.mfa')
                    ->name('pending.validate');

                Route::post('/{environment}/activate', [MailSettingController::class, 'activate'])
                    ->whereIn('environment', ['test', 'live'])
                    ->middleware('mail.mfa')
                    ->name('activate');

                Route::post('/{environment}/rollback', [MailSettingController::class, 'rollback'])
                    ->whereIn('environment', ['test', 'live'])
                    ->middleware('mail.mfa')
                    ->name('rollback');
            });
    });
});

/*
|--------------------------------------------------------------------------
| Event Management
|--------------------------------------------------------------------------
|
| URLs remain:
| /events/create
| /events/{event}/edit
| etc.
|
*/

Route::middleware(['auth:admin', 'no-cache'])->group(function () {
    Route::resource('events', EventController::class)->except(['index', 'show']);

    Route::prefix('stripe')->name('stripe.')->group(function () {
        Route::get('/onboard', [StripeConnectController::class, 'start'])
            ->name('onboard');

        Route::get('/onboard/refresh', [StripeConnectController::class, 'refresh'])
            ->name('onboard.refresh');

        Route::get('/onboard/return', [StripeConnectController::class, 'handleReturn'])
            ->name('onboard.return');
    });
});
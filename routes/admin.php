<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\AdminAuthController;
use App\Http\Controllers\admin\StripeConnectController;
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

// Admin/vendor auth (prefixed with /admin)
Route::prefix('admin')->name('admin.')->group(function () {

    // Guests (not logged in as admin) — login form & submit
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    // Logged-in admins/vendors — logout
    Route::middleware(['auth:admin', 'no-cache'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });

});

// Event management (URLs stay at /events/... so route names match the existing blade views)
// Only admin-guard users (admin or vendor) can create/edit/update/delete.
Route::middleware(['auth:admin', 'no-cache'])->group(function () {
    Route::resource('events', EventController::class)->except(['index', 'show']);

    Route::prefix('stripe')->name('stripe.')->group(function () {
        Route::get('/onboard', [StripeConnectController::class, 'start'])->name('onboard');
        Route::get('/onboard/refresh', [StripeConnectController::class, 'refresh'])->name('onboard.refresh');
        Route::get('/onboard/return', [StripeConnectController::class, 'handleReturn'])->name('onboard.return');
    });
});

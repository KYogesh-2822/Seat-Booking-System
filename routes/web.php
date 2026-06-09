<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\booking\BookingController;

/*
|--------------------------------------------------------------------------
| Public + Regular User Routes
|--------------------------------------------------------------------------
|
| Admin/vendor-only routes live in routes/admin.php.
|
*/
 // Public
  Route::get('/', [EventController::class, 'index'])->name('home');

  Route::get('/events/{event}', [EventController::class, 'show'])
      ->whereNumber('event')
      ->name('events.show');

  // Public polling endpoint — browsers fetch fresh seat statuses every few seconds so locks made in other browsers show up here.
  Route::get('/events/{event}/seat-statuses', [BookingController::class, 'seatStatuses'])
      ->whereNumber('event')
      ->name('events.seat-statuses');

  // Booking initiate — accessible to both guests and logged-in users
  // (controller decides: guest → save session + redirect to login, logged-in → lock seats)
  Route::post('/booking/initiate', [BookingController::class, 'initiate'])
      ->name('booking.initiate');

  // Guest-only user routes
  Route::middleware('guest:web')->group(function () {
      Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
      Route::post('/register', [AuthController::class, 'register']);

      Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
      Route::post('/login', [AuthController::class, 'login']);
  });

  // Logout — user guard only
  Route::middleware(['auth:web'])->group(function () {
      Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
  });

  // Booking confirm/cancel — user OR admin/vendor can book
  Route::middleware(['auth:web,admin'])->group(function () {
      Route::get('/booking/confirm/{event}', [BookingController::class, 'showConfirmation'])
          ->whereNumber('event')
          ->name('booking.confirm');

      Route::post('/booking/confirm/{event}', [BookingController::class, 'confirmBooking'])
          ->whereNumber('event')
          ->name('booking.confirmBooking');

      Route::post('/booking/cancel/{event}', [BookingController::class, 'cancel'])
          ->whereNumber('event')
          ->name('booking.cancel');

      // AJAX lock / unlock — called when user clicks a seat on the event page.
      Route::post('/seats/{seat}/lock', [BookingController::class, 'lockSeat'])
          ->whereNumber('seat')
          ->name('seats.lock');

      Route::delete('/seats/{seat}/unlock', [BookingController::class, 'unlockSeat'])
          ->whereNumber('seat')
          ->name('seats.unlock');
  });

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

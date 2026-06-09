<?php

namespace App\Http\Controllers\booking;

use App\Http\Controllers\Controller;
use App\Services\SeatBookingService;
use App\Services\StripePaymentService;
use App\Support\Booker;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Seat;

class BookingController extends Controller
{
    protected $seatBookingService;

    public function __construct(SeatBookingService $seatBookingService, StripePaymentService $stripePaymentService)
    {
        $this->seatBookingService = $seatBookingService;
        $this->stripePaymentService = $stripePaymentService;
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'seat_ids' => 'required|string',
        ]);

        $seatIds = array_map('intval', explode(',', $request->seat_ids));
        $seatIds = array_filter($seatIds);

        if (empty($seatIds)) {
            return back()->with('error', 'Please select at least one seat.');
        }

        $booker = Booker::current();

        // Guest: save to session and redirect to login
        if (!$booker) {
            session([
                'pending_booking' => [
                    'event_id' => $request->event_id,
                    'seat_ids' => $seatIds,
                ]
            ]);

            return redirect()
                ->route('login')
                ->with('error', 'Please login to continue booking your seats.');
        }

        $result = $this->seatBookingService->lockSeats(
            (int) $request->event_id,
            $seatIds,
            $booker['id'],
            $booker['type']
        );

        if (!$result['success']) {
            return redirect()
                ->route('events.show', $request->event_id)
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('booking.confirm', $request->event_id)
            ->with('success', 'Seats locked! Please complete booking within ' .
                config('booking.lock_duration_minutes', 5) . ' minutes.');
    }

    public function showConfirmation(Event $event)
    {
        $booker = Booker::current();

        $lockedSeats = $this->seatBookingService->getLockedSeatsForUser(
            $event->id,
            $booker['id'],
            $booker['type']
        );

        if ($lockedSeats->isEmpty()) {
            return redirect()
                ->route('events.show', $event->id)
                ->with('error', 'No active locked seats. Please select seats again.');
        }

        $expiresAt = $lockedSeats->min('lock_expires_at');

        return view('booking.confirm', [
            'event' => $event,
            'seats' => $lockedSeats,
            'expiresAt' => $expiresAt,
        ]);
    }

    public function confirmBooking(Event $event)
    {
        $booker = Booker::current();

        $lockedSeats = $this->seatBookingService->getLockedSeatsForUser(
            $event->id,
            $booker['id'],
            $booker['type']
        );

        if ($lockedSeats->isEmpty()) {
            return redirect()
                ->route('events.show', $event->id)
                ->with('error', 'No active locked seats. Please select seats again.');
        }

        $vendor = $event->vendor;

        if (!$vendor || !$vendor->isStripeReady()) {
            return redirect()
                ->route('events.show', $event->id)
                ->with('error', 'This vendor has not completed Stripe onboarding yet.');
        }

        try {
            $session = $this->stripePaymentService->createCheckoutSession($event, $lockedSeats, $booker);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('events.show', $event->id)
                ->with('error', 'Unable to start Stripe payment: ' . $exception->getMessage());
        }

        return redirect($session->url);
    }

    // AJAX: lock a single seat when user clicks it. Returns JSON so the JS can update the UI without a page reload.
    public function lockSeat(Seat $seat)
    {
        $booker = Booker::current();

        if (!$booker) {
            return response()->json(['success' => false, 'message' => 'Please login to select seats.'], 401);
        }

        $result = $this->seatBookingService->lockSeats(
            $seat->event_id,
            [$seat->id],
            $booker['id'],
            $booker['type']
        );

        return response()->json($result, $result['success'] ? 200 : 409);
    }

    // AJAX: release a single seat (user clicked an already-selected seat to deselect it).
    public function unlockSeat(Seat $seat)
    {
        $booker = Booker::current();

        if (!$booker) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $result = $this->seatBookingService->releaseSingleSeatLock(
            $seat->event_id,
            $seat->id,
            $booker['id'],
            $booker['type']
        );

        return response()->json($result, $result['success'] ? 200 : 403);
    }

    // AJAX polling endpoint: returns current status of every seat for this event.
    // The `mine` flag lets the browser show the current user's own locks as "selected" (yellow) rather than "locked" (gray).
    public function seatStatuses(Event $event)
    {
        $booker = Booker::current();

        $seats = $event->seats()->get(['id', 'status', 'locked_by', 'locked_by_type']);

        $payload = $seats->map(function ($seat) use ($booker) {
            $mine = $booker
                && $seat->status === 'locked'
                && $seat->locked_by === $booker['id']
                && $seat->locked_by_type === $booker['type'];

            return [
                'id' => $seat->id,
                'status' => $seat->status,
                'mine' => $mine,
            ];
        });

        return response()->json(['seats' => $payload]);
    }

    public function cancel(Event $event)
    {
        $booker = Booker::current();

        $this->seatBookingService->releaseUserLocks(
            $event->id,
            $booker['id'],
            $booker['type']
        );

        return redirect()
            ->route('events.show', $event->id)
            ->with('success', 'Your seat selection has been cancelled.');
    }
}

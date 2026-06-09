<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Seat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Booking;

class SeatBookingService
{
    public function lockSeats(int $eventId, array $seatIds, int $bookerId, string $bookerType): array
    {
        return DB::transaction(function () use ($eventId, $seatIds, $bookerId, $bookerType) {

            $seats = Seat::whereIn('id', $seatIds)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                return [
                    'success' => false,
                    'message' => 'One or more selected seats do not belong to this event.',
                ];
            }

            $unavailable = [];

            foreach ($seats as $seat) {
                $isAvailable = $seat->status === 'available';
                $isOwnedByMe = $seat->status === 'locked'
                    && $seat->locked_by === $bookerId
                    && $seat->locked_by_type === $bookerType
                    && $seat->lock_expires_at
                    && Carbon::parse($seat->lock_expires_at)->isFuture();

                if (!$isAvailable && !$isOwnedByMe) {
                    $unavailable[] = $seat->seat_row . $seat->seat_column;
                }
            }

            if (!empty($unavailable)) {
                return [
                    'success' => false,
                    'message' => 'These seats are no longer available: ' . implode(', ', $unavailable),
                ];
            }

            $lockDuration = config('booking.lock_duration_minutes', 5);
            $expiresAt = now()->addMinutes($lockDuration);

            Seat::whereIn('id', $seatIds)->update([
                'status' => 'locked',
                'locked_by' => $bookerId,
                'locked_by_type' => $bookerType,
                'lock_expires_at' => $expiresAt,
            ]);

            Cache::forget("event.{$eventId}.seats");

            return [
                'success' => true,
                'expires_at' => $expiresAt,
                'seat_ids' => $seatIds,
            ];
        });
    }

    public function getLockedSeatsForUser(int $eventId, int $bookerId, string $bookerType)
    {
        return Seat::where('event_id', $eventId)
            ->where('locked_by', $bookerId)
            ->where('locked_by_type', $bookerType)
            ->where('status', 'locked')
            ->where('lock_expires_at', '>', now())
            ->orderBy('seat_row')
            ->orderBy('seat_column')
            ->get();
    }

    public function confirmBooking(int $eventId, int $bookerId, string $bookerType): array
    {
        return DB::transaction(function () use ($eventId, $bookerId, $bookerType) {


            $event = Event::find($eventId);
            if (!$event) {
                return [
                    'success' => false,
                    'message' => 'Event not found. Please try again.',
                ];
            }

            $seats = Seat::where('event_id', $eventId)
                ->where('locked_by', $bookerId)
                ->where('locked_by_type', $bookerType)
                ->where('status', 'locked')
                ->lockForUpdate()
                ->get();

            if ($seats->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No locked seats found. Please select seats again.',
                ];
            }

            foreach ($seats as $seat) {
                if (!$seat->lock_expires_at || Carbon::parse($seat->lock_expires_at)->isPast()) {
                    return [
                        'success' => false,
                        'message' => 'Your lock has expired. Please select seats again.',
                    ];
                }
            }

            $seatIds = $seats->pluck('id')->toArray();

            Seat::whereIn('id', $seatIds)->update([
                'status' => 'booked',
                'lock_expires_at' => null,
            ]);

            $bookingData = [];
            foreach ($seats as $seat) {
                $bookingData[] = [
                    'user_id' => $bookerId,
                    'booker_type' => $bookerType,
                    'seat_id' => $seat->id,
                    'event_id' => $eventId,
                    'booked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'total_amount' => $event->ticket_price,
                    'currency' => $event->currency ?? 'inr',
                    'payment_status' => 'pending',
                ];
            }

            Booking::insert($bookingData);

            Cache::forget("event.{$eventId}.seats");

            return [
                'success' => true,
                'seat_count' => $seats->count(),
            ];
        });
    }

    public function finalizeStripeBooking(
        int $eventId,
        array $seatIds,
        int $bookerId,
        string $bookerType,
        string $checkoutSessionId,
        string $paymentIntentId,
        int $stripeAmount,
        string $currency,
        array $stripePayload
    ): array {
        if (Booking::where('stripe_checkout_session_id', $checkoutSessionId)->exists()) {
            return [
                'success' => true,
                'message' => 'Booking already completed for this Stripe session.',
            ];
        }

        return DB::transaction(function () use (
            $eventId,
            $seatIds,
            $bookerId,
            $bookerType,
            $checkoutSessionId,
            $paymentIntentId,
            $currency,
            $stripePayload
        ) {
            $event = Event::find($eventId);

            if (!$event) {
                return [
                    'success' => false,
                    'message' => 'Event not found.',
                ];
            }

            $seats = Seat::whereIn('id', $seatIds)
                ->where('event_id', $eventId)
                ->where('locked_by', $bookerId)
                ->where('locked_by_type', $bookerType)
                ->where('status', 'locked')
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                return [
                    'success' => false,
                    'message' => 'One or more seats are no longer reserved.',
                ];
            }

            foreach ($seats as $seat) {
                if (!$seat->lock_expires_at || Carbon::parse($seat->lock_expires_at)->isPast()) {
                    return [
                        'success' => false,
                        'message' => 'A seat reservation expired before payment was completed.',
                    ];
                }
            }

            $ticketPrice = (int) $event->ticket_price;
            $platformFeePerSeat = (int) round($ticketPrice * config('services.stripe.platform_commission_percent', 10) / 100);
            $vendorAmountPerSeat = $ticketPrice - $platformFeePerSeat;

            Seat::whereIn('id', $seatIds)->update([
                'status' => 'booked',
                'locked_by' => null,
                'locked_by_type' => null,
                'lock_expires_at' => null,
            ]);

            $bookingData = [];
            foreach ($seats as $seat) {
                $bookingData[] = [
                    'user_id' => $bookerId,
                    'booker_type' => $bookerType,
                    'seat_id' => $seat->id,
                    'event_id' => $eventId,
                    'booked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'total_amount' => $ticketPrice,
                    'currency' => $currency,
                    'payment_gateway' => 'stripe',
                    'payment_status' => 'paid',
                    'stripe_checkout_session_id' => $checkoutSessionId,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'platform_fee_amount' => $platformFeePerSeat,
                    'vendor_amount' => $vendorAmountPerSeat,
                    'paid_at' => now(),
                    'stripe_payload' => $stripePayload,
                ];
            }

            Booking::insert($bookingData);
            Cache::forget("event.{$eventId}.seats");

            return [
                'success' => true,
                'seat_count' => $seats->count(),
            ];
        });
    }

    // Release a single seat's lock (used when user clicks an already-selected seat to deselect it).
    // Guards: seat must belong to this event AND currently be locked by this same booker — prevents one user cancelling another's lock.
    public function releaseSingleSeatLock(int $eventId, int $seatId, int $bookerId, string $bookerType): array
    {
        return DB::transaction(function () use ($eventId, $seatId, $bookerId, $bookerType) {

            $seat = Seat::where('id', $seatId)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if (!$seat) {
                return ['success' => false, 'message' => 'Seat not found for this event.'];
            }

            $isOwnedByMe = $seat->status === 'locked'
                && $seat->locked_by === $bookerId
                && $seat->locked_by_type === $bookerType;

            if (!$isOwnedByMe) {
                return ['success' => false, 'message' => 'You cannot release a seat you do not own.'];
            }

            $seat->update([
                'status' => 'available',
                'locked_by' => null,
                'locked_by_type' => null,
                'lock_expires_at' => null,
            ]);

            Cache::forget("event.{$eventId}.seats");

            return ['success' => true, 'message' => 'Seat released.'];
        });
    }

    public function releaseUserLocks(int $eventId, int $bookerId, string $bookerType): int
    {
        $affected = Seat::where('event_id', $eventId)
            ->where('locked_by', $bookerId)
            ->where('locked_by_type', $bookerType)
            ->where('status', 'locked')
            ->update([
                'status' => 'available',
                'locked_by' => null,
                'locked_by_type' => null,
                'lock_expires_at' => null,
            ]);

        Cache::forget("event.{$eventId}.seats");

        return $affected;
    }

    public function releaseExpiredLocks(): int
    {
        $expiredSeats = Seat::where('status', 'locked')
            ->where('lock_expires_at', '<=', now())
            ->get();

        if ($expiredSeats->isEmpty()) {
            return 0;
        }

        $eventIds = $expiredSeats->pluck('event_id')->unique();

        $affected = Seat::where('status', 'locked')
            ->where('lock_expires_at', '<=', now())
            ->update([
                'status' => 'available',
                'locked_by' => null,
                'locked_by_type' => null,
                'lock_expires_at' => null,
            ]);

        foreach ($eventIds as $eventId) {
            Cache::forget("event.{$eventId}.seats");
        }

        return $affected;
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\SeatBookingService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeService $stripeService, SeatBookingService $seatBookingService): Response
    {
        try {
            $event = $stripeService->constructWebhookEvent(
                $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (SignatureVerificationException $exception) {
            return response('Webhook signature verification failed.', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response('OK', 200);
        }

        $session = $event->data->object;

        if (($session->payment_status ?? null) !== 'paid') {
            return response('OK', 200);
        }

        $metadata = $session->metadata ?? [];
        $eventId = (int) ($metadata['event_id'] ?? $metadata->event_id ?? 0);
        $seatIds = explode(',', (string) ($metadata['seat_ids'] ?? $metadata->seat_ids ?? ''));
        $bookerId = (int) ($metadata['booker_id'] ?? $metadata->booker_id ?? 0);
        $bookerType = (string) ($metadata['booker_type'] ?? $metadata->booker_type ?? '');

        if (!$eventId || empty($seatIds) || !$bookerId || !$bookerType) {
            return response('OK', 200);
        }

        $seatBookingService->finalizeStripeBooking(
            $eventId,
            array_filter($seatIds, 'strlen'),
            $bookerId,
            $bookerType,
            $session->id,
            $session->payment_intent ?? '',
            (int) ($session->amount_total ?? 0),
            (string) ($session->currency ?? ''),
            $event->toArray()
        );

        return response('OK', 200);
    }
}

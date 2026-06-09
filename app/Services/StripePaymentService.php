<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Collection;
use Stripe\Checkout\Session;
use Stripe\Stripe;
class StripePaymentService
{
    public function __construct(protected StripeService $stripeService)
    {
    }

    public function createCheckoutSession(Event $event, Collection $seats, array $booker): Session
    {
        $seatCount = $seats->count();
        $currency = $event->currency ?: config('services.stripe.currency');
        $unitAmount = $this->stripeService->toStripeAmount((int) $event->ticket_price, $currency);
        $platformFee = (int) round(((int) $event->ticket_price * $seatCount) * config('services.stripe.platform_commission_percent', 10) / 100);
        $platformFeeAmount = $this->stripeService->toStripeAmount($platformFee, $currency);

        $metadata = [
            'event_id' => (string) $event->id,
            'seat_ids' => $seats->pluck('id')->implode(','),
            'booker_id' => (string) $booker['id'],
            'booker_type' => $booker['type'],
        ];

        return $this->stripeService->createCheckoutSession([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => $event->event_name,
                        'description' => sprintf('%s - %s seats', $event->event_name, $seatCount),
                    ],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => $seatCount,
            ]],
            'payment_intent_data' => [
                'application_fee_amount' => $platformFeeAmount,
                'transfer_data' => [
                    'destination' => $event->vendor?->stripe_account_id,
                ],
            ],
            'metadata' => $metadata,
            'success_url' => route('events.show', $event->id) . '?payment=success',
            'cancel_url' => route('booking.confirm', $event->id) . '?payment=cancelled',
        ]);
    }
}

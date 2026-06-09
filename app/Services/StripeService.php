<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Log;
use Stripe\AccountLink;
use Stripe\Checkout\Session;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    private StripeClient $client;

    private array $zeroDecimalCurrencies = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
        'pyg', 'rwf', 'vnd', 'vuv', 'xaf', 'xcd', 'xof', 'xpf',
    ];

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function createConnectedAccount(Admin $admin)
    {
        return $this->client->accounts->create([
            'type' => 'express',
            'country' => 'IN',
            'email' => $admin->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);
    }

    public function createAccountLink(string $accountId): AccountLink
    {
        return $this->client->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => route('admin.stripe.onboard.refresh'),
            'return_url' => route('admin.stripe.onboard.return'),
            'type' => 'account_onboarding',
        ]);
    }

    public function retrieveAccount(string $accountId)
    {
        return $this->client->accounts->retrieve($accountId, []);
    }

    public function toStripeAmount(int $amount, string $currency): int
    {
        $currency = strtolower($currency);

        if (in_array($currency, $this->zeroDecimalCurrencies, true)) {
            return $amount;
        }

        return $amount * 100;
    }

    public function fromStripeAmount(int $amount, string $currency): int
    {
        $currency = strtolower($currency);

        if (in_array($currency, $this->zeroDecimalCurrencies, true)) {
            return $amount;
        }

        return intdiv($amount, 100);
    }

    public function createCheckoutSession(array $sessionData): Session
    {
        return $this->client->checkout->sessions->create($sessionData);
    }

    public function constructWebhookEvent(string $payload, string $signatureHeader): StripeEvent
    {
        return Webhook::constructEvent(
            $payload,
            $signatureHeader,
            config('services.stripe.webhook_secret')
        );
    }
}

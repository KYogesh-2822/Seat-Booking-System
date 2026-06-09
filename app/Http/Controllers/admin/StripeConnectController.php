<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class StripeConnectController extends Controller
{
    public function __construct(protected StripeService $stripeService)
    {
    }

    public function start(): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            abort(403);
        }

        if (!$admin->stripe_account_id) {
            $account = $this->stripeService->createConnectedAccount($admin);
            $admin->update(['stripe_account_id' => $account->id]);
        }

        $accountLink = $this->stripeService->createAccountLink($admin->stripe_account_id);

        return redirect($accountLink->url);
    }

    public function refresh(): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || !$admin->stripe_account_id) {
            return redirect()->route('home')->with('error', 'Stripe onboarding cannot be refreshed before an account is created.');
        }

        $accountLink = $this->stripeService->createAccountLink($admin->stripe_account_id);

        return redirect($accountLink->url);
    }

    public function handleReturn(): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin || !$admin->stripe_account_id) {
            return redirect()->route('home')->with('error', 'Stripe onboarding status could not be verified.');
        }

        $account = $this->stripeService->retrieveAccount($admin->stripe_account_id);

        $admin->update([
            'stripe_details_submitted' => (bool) ($account->details_submitted ?? false),
            'stripe_charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'stripe_payouts_enabled' => (bool) ($account->payouts_enabled ?? false),
            'stripe_onboarded_at' => now(),
        ]);

        return redirect()->route('admin.events.index')->with('success', 'Stripe onboarding status refreshed.');
    }
}

<?php

namespace Modules\Checkout\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Lunar\Facades\CartSession;
use Lunar\Models\Country;
use Lunar\Models\Order;

class CheckoutController extends Controller
{
    /**
     * Checkout page shell. The Vue island drives the steps via /api/v1/checkout.
     */
    public function index(): View|RedirectResponse
    {
        $cart = CartSession::current();

        if (! $cart || $cart->lines->isEmpty()) {
            return redirect()->route('storefront.home');
        }

        return view('theme::pages.checkout', [
            // Embedded for the island's address form (not SEO content).
            'countries' => Country::orderBy('name')->get(['id', 'name'])->toArray(),
        ]);
    }

    /**
     * Order confirmation page.
     */
    public function confirmation(string $reference): View
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        return view('theme::pages.checkout-confirmation', [
            'order' => $order->load('lines'),
        ]);
    }
}

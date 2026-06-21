<?php

namespace Modules\Checkout\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Lunar\Facades\CartSession;
use Modules\Location\Services\CountryService;
use Modules\Order\Services\OrderService;

class CheckoutController extends Controller
{
    public function __construct(
        protected CountryService $countries,
        protected OrderService $orders,
    ) {}

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
            'countries' => $this->countries->forSelect(),
            // Only offer VNPay when merchant credentials are configured.
            'vnpayEnabled' => filled(config('payment.vnpay.tmn_code')),
        ]);
    }

    /**
     * Order confirmation page.
     */
    public function confirmation(string $reference): View
    {
        $order = $this->orders->findByReference($reference);

        abort_if($order === null, 404);

        return view('theme::pages.checkout-confirmation', [
            'order' => $order,
        ]);
    }
}

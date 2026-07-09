<?php

namespace Modules\Checkout\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Lunar\Facades\CartSession;
use Modules\Checkout\Http\Requests\PlaceOrderRequest;
use Modules\Checkout\Services\CheckoutService;
use Modules\Customer\Services\CountryService;
use Modules\Customer\Services\LocationService;
use Modules\Order\Services\OrderService;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
        protected CountryService $countries,
        protected LocationService $locations,
        protected OrderService $orders,
    ) {}

    /**
     * Checkout page (SSR Blade). One form submits address + shipping + payment
     * together (see place()), so there's no multi-step client state to get out
     * of sync. Province→ward dropdowns are progressively enhanced by vanilla JS.
     */
    public function index(): View|RedirectResponse
    {
        $cart = CartSession::current();

        if (! $cart || $cart->lines->isEmpty()) {
            return redirect()->route('storefront.home');
        }

        return view('theme::pages.checkout', [
            'cart' => $cart,
            'countries' => $this->countries->forSelect(),
            'provinces' => $this->locations->provinces(),
            'shippingOptions' => $this->checkout->shippingOptions(),
            'old' => session()->getOldInput(),
            // vnpayEnabled / momoEnabled / defaultPayment
            ...$this->checkout->paymentContext(),
        ]);
    }

    /**
     * POST /checkout — place the order from a single SSR form. Runs the whole
     * Lunar flow server-side (addresses → shipping → authorize → order), so the
     * "Enter your address first" multi-step race can't happen.
     */
    public function place(PlaceOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $cart = CartSession::current();
        if (! $cart || $cart->lines->isEmpty()) {
            return redirect()->route('storefront.home');
        }

        try {
            $this->checkout->setAddresses($request->addressData());
            $this->checkout->setShipping($data['shipping_option']);
            $order = $this->checkout->placeOrder($data['payment_type']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Online gateway: redirect to the provider. Offline (cod/bank): confirmation.
        try {
            $payUrl = $this->checkout->paymentRedirectUrl($order, $data['payment_type'], (string) $request->ip());
        } catch (\Throwable $e) {
            return redirect()->route('storefront.checkout.confirmation', $order->reference)
                ->with('error', $e->getMessage());
        }

        return $payUrl
            ? redirect()->away($payUrl)
            : redirect()->route('storefront.checkout.confirmation', $order->reference);
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

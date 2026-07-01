<?php

namespace Modules\Checkout\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Lunar\Facades\CartSession;
use Modules\Checkout\Services\CheckoutService;
use Modules\Customer\Models\Province;
use Modules\Customer\Services\CountryService;
use Modules\Order\Services\OrderService;
use Modules\Checkout\Services\VNPayGateway;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
        protected CountryService $countries,
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
            'provinces' => Province::orderBy('name')->get(['id', 'code', 'name']),
            'shippingOptions' => $this->checkout->shippingOptions(),
            'vnpayEnabled' => filled(config('payment.vnpay.tmn_code')),
            'old' => session()->getOldInput(),
        ]);
    }

    /**
     * POST /checkout — place the order from a single SSR form. Runs the whole
     * Lunar flow server-side (addresses → shipping → authorize → order), so the
     * "Enter your address first" multi-step race can't happen.
     */
    public function place(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'line_one' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],   // Tỉnh/Thành
            'city' => ['required', 'string', 'max:255'],     // Phường/Xã
            'country_id' => ['required', 'integer'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'shipping_option' => ['required', 'string'],
            'payment_type' => ['required', 'string', \Illuminate\Validation\Rule::in($this->checkout->paymentMethods())],
        ]);

        $cart = CartSession::current();
        if (! $cart || $cart->lines->isEmpty()) {
            return redirect()->route('storefront.home');
        }

        try {
            $this->checkout->setAddresses([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'line_one' => $data['line_one'],
                'state' => $data['state'],
                'city' => $data['city'],
                'country_id' => $data['country_id'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
            ]);

            $this->checkout->setShipping($data['shipping_option']);

            $order = $this->checkout->placeOrder($data['payment_type']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Online gateway: redirect to VNPay. Offline (cod/bank): confirmation.
        if ($data['payment_type'] === 'vnpay') {
            $gateway = VNPayGateway::fromConfig();
            if ($gateway->isConfigured()) {
                return redirect()->away($gateway->buildPaymentUrl($order, $request->ip()));
            }
        }

        return redirect()->route('storefront.checkout.confirmation', $order->reference);
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

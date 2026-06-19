<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Lunar\Models\Order;
use Modules\Payment\Services\VNPayGateway;
use Modules\Payment\Services\VNPayPaymentProcessor;

/**
 * VNPay redirect + callbacks.
 *   start  — build the signed redirect URL for an awaiting-payment order.
 *   return — browser comes back here after paying; verify + reconcile + redirect
 *            to the storefront confirmation page.
 *   ipn    — server-to-server notification; the source of truth for marking paid.
 *            Responds with VNPay's expected {RspCode, Message} JSON.
 */
class VNPayController extends Controller
{
    public function __construct(
        protected VNPayPaymentProcessor $processor,
    ) {}

    /**
     * GET /payment/vnpay/start/{order} — returns the redirect URL (JSON) so the
     * checkout island can send the shopper to VNPay.
     */
    public function start(Request $request, int $order): JsonResponse
    {
        $gateway = VNPayGateway::fromConfig();

        if (! $gateway->isConfigured()) {
            return response()->json(['message' => 'VNPay is not configured.'], 422);
        }

        $model = Order::findOrFail($order);

        return response()->json([
            'data' => ['redirect_url' => $gateway->buildPaymentUrl($model, $request->ip())],
        ]);
    }

    /**
     * GET /payment/vnpay/return — shopper redirected back from VNPay.
     */
    public function return(Request $request): RedirectResponse
    {
        $result = $this->processor->reconcile($request->query());

        if (! $result->verified) {
            return redirect()->route('storefront.checkout')
                ->with('error', 'Payment verification failed. Please try again.');
        }

        if (! $result->paid) {
            return redirect()->route('storefront.checkout')
                ->with('error', 'Payment was not completed.');
        }

        return redirect()->route('storefront.checkout.confirmation', $result->order->reference);
    }

    /**
     * GET /payment/vnpay/ipn — VNPay server-to-server confirmation.
     */
    public function ipn(Request $request): JsonResponse
    {
        $result = $this->processor->reconcile($request->query());

        if (! $result->verified) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        if (! $result->order) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        if ($result->alreadyProcessed) {
            return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }
}

<?php

namespace Modules\Checkout\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Checkout\Services\MoMoPaymentProcessor;

/**
 * MoMo redirect + callbacks (mirrors the VNPay controller).
 *   return — browser comes back here after paying (query params); verify +
 *            reconcile + redirect to the storefront confirmation page.
 *   ipn    — server-to-server notification (JSON POST); the source of truth for
 *            marking paid. Responds 204 No Content as MoMo expects.
 *
 * The redirect URL (payUrl) is created in the checkout `place()` flow via
 * MoMoGateway, so there's no `start` endpoint here.
 */
class MoMoController extends Controller
{
    public function __construct(
        protected MoMoPaymentProcessor $processor,
    ) {}

    /**
     * GET /payment/momo/return — shopper redirected back from MoMo.
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
     * POST /payment/momo/ipn — MoMo server-to-server confirmation (JSON body).
     * MoMo expects an empty 204 response on receipt.
     */
    public function ipn(Request $request): Response|JsonResponse
    {
        $this->processor->reconcile($request->all());

        return response()->noContent();
    }
}

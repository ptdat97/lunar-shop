<?php

namespace Modules\Order\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Order;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Services\InvoiceService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an order invoice PDF to the authenticated owner. `{order}` is
 * route-model-bound to the Lunar Order; ownership is enforced by matching the
 * order's customer to the signed-in user's customer (guests / non-owners get a
 * 404 so nothing is revealed).
 */
class InvoiceController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
        protected InvoiceService $invoices,
    ) {}

    public function __invoke(Request $request, Order $order): Response
    {
        $user = $request->user();
        abort_unless($user, 404); // guests get 404 (no login route to redirect to)

        $customer = $this->customers->existingForUser($user);
        abort_unless($customer && (int) $order->customer_id === (int) $customer->id, 404);

        return $this->invoices->make($order)
            ->download($this->invoices->filename($order));
    }
}

<?php

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Http\Resources\CustomerOrderPage;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\OrderService;
use Modules\Order\Services\OrderTimeline;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected CustomerResolver $customers,
    ) {}

    /**
     * List orders for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        return CustomerOrderPage::for(
            $request,
            $this->customers->existingForUser($request->user()),
            $this->orders,
        );
    }

    /**
     * Show a single order.
     */
    public function show(Request $request, int $id): OrderResource|JsonResponse
    {
        $customer = $this->customers->existingForUser($request->user());

        abort_unless($customer, 404);

        $order = $this->orders->findForCustomer($id, $customer->id);

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return new OrderResource($order);
    }

    /**
     * GET /api/v1/orders/{id}/timeline — the order's status history.
     *
     * Read from Lunar's activity log; there is no timeline table to keep in sync.
     */
    public function timeline(Request $request, int $id, OrderTimeline $timeline): JsonResponse
    {
        $customer = $this->customers->existingForUser($request->user());

        abort_unless($customer, 404);

        $order = $this->orders->findForCustomer($id, $customer->id);

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['data' => $timeline->for($order)]);
    }
}

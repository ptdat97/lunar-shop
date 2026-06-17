<?php

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Order\Services\OrderService;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orders,
    ) {}

    /**
     * List orders for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return response()->json(['data' => [], 'message' => 'No customer profile.'], 200);
        }

        $orders = $this->orders->customerOrders($customer->id);

        return response()->json(['data' => $orders]);
    }

    /**
     * Show a single order.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $request->user()->customer;

        abort_unless($customer, 404);

        $order = $this->orders->findForCustomer($id, $customer->id);

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['data' => $order]);
    }
}
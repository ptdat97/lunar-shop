<?php

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\OrderService;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected CustomerResolver $customers,
    ) {}

    /**
     * List orders for the authenticated customer.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $customer = $this->customers->existingForUser($request->user());

        if (! $customer) {
            return response()->json(['data' => []], 200);
        }

        return OrderResource::collection($this->orders->customerOrders($customer->id));
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
}

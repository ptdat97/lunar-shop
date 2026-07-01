<?php

namespace Modules\Order\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Order;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Services\ReturnService;

/**
 * Customer-facing return (RMA) requests. Owner-only: a customer can only open a
 * return against their own paid order. Called from the account order-detail UI.
 */
class ReturnController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
        protected ReturnService $returns,
    ) {}

    /**
     * POST /account/orders/{order}/returns — open a return request.
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 404);

        $customer = $this->customers->existingForUser($user);
        abort_unless($customer && (int) $order->customer_id === (int) $customer->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $return = $this->returns->open($order, $data['lines'], $data['reason'], $data['comment'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'reference' => $return->reference,
                'status' => $return->status,
            ],
        ], 201);
    }
}

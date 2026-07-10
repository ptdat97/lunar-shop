<?php

namespace Modules\Inventory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Inventory\Http\Requests\NotifyMeRequest;
use Modules\Inventory\Services\StockNotificationService;

/**
 * "Notify me when back in stock" subscription endpoint.
 *
 * POST /api/v1/inventory/notify-me  { variant_id, email }
 */
class StockNotificationController extends Controller
{
    public function __construct(
        protected StockNotificationService $subscriptions,
    ) {}

    public function store(NotifyMeRequest $request): JsonResponse
    {
        // The "is it out of stock?" rule lives in the service, which aborts 422.
        $this->subscriptions->subscribe(
            $request->integer('variant_id'),
            $request->string('email')->toString(),
        );

        // An acknowledgement, not a resource: the subscription row is private
        // bookkeeping the shopper never addresses again.
        return response()->json([
            'message' => "We'll email you when this item is back in stock.",
        ], 201);
    }
}

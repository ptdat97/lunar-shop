<?php

namespace Modules\Inventory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Services\InventoryService;
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
        protected InventoryService $inventory,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $variant = ProductVariant::findOrFail($data['variant_id']);

        // Only meaningful for variants that aren't currently purchasable —
        // there's nothing to wait for if it's in stock.
        if ($this->inventory->inStock($variant->id)) {
            return response()->json([
                'message' => 'This item is already in stock.',
            ], 422);
        }

        $this->subscriptions->subscribe($variant, $data['email']);

        return response()->json([
            'message' => "We'll email you when this item is back in stock.",
        ], 201);
    }
}

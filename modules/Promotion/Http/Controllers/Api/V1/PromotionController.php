<?php

namespace Modules\Promotion\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\CustomerResolver;
use Modules\Promotion\Http\Resources\PromotionResource;
use Modules\Promotion\Services\MembershipService;
use Modules\Promotion\Services\PromotionService;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionService $promotions,
        protected MembershipService $membership,
        protected CustomerResolver $customers,
    ) {
    }

    /**
     * The authenticated customer's membership tier + progress to the next one.
     */
    public function membership(Request $request): JsonResponse
    {
        $customer = $this->customers->existingForUser($request->user());

        if (! $customer) {
            return response()->json(['data' => null]);
        }

        $current = $this->membership->currentTier($customer);
        $next = $this->membership->nextTierProgress($customer);

        return response()->json([
            'data' => [
                'enabled' => $this->membership->enabled(),
                'tier' => $current ? [
                    'name' => $current['name'],
                    'discount_percentage' => $current['discount_percentage'] ?? null,
                ] : null,
                'lifetime_spend' => $this->membership->lifetimeSpend($customer),
                'next_tier' => $next ? [
                    'name' => $next['tier']['name'],
                    'remaining' => $next['remaining_minor'],
                ] : null,
            ],
        ]);
    }

    /**
     * Active automatic promotions + the current flash sale (for storefront
     * banners/badges and app/headless clients).
     */
    public function index(): JsonResponse
    {
        $flashSale = $this->promotions->currentFlashSale();

        return response()->json([
            'data' => PromotionResource::collection($this->promotions->activeAutomatic()),
            'meta' => [
                'flash_sale' => $flashSale ? (new PromotionResource($flashSale))->resolve() : null,
            ],
        ]);
    }
}

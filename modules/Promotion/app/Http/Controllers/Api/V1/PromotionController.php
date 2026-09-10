<?php

namespace Modules\Promotion\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\Models\Currency;
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
    ) {}

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
                    // Số thô (đơn vị nhỏ nhất) vẫn giữ cho thanh tiến độ.
                    'remaining' => $next['remaining_minor'],
                    // ...còn chuỗi để HIỂN THỊ thì server định dạng, đúng mẫu
                    // CartResource::freeShippingInfo(). Trước đây JS tự định
                    // dạng bằng `formatVnd()`, ghim cứng VND và chia cho 100 —
                    // sai hai lần: sai tiền tệ nếu shop dùng loại khác, và sai
                    // 100 lần nếu shop dùng chính VND, vì VND có 0 chữ số thập
                    // phân chứ không phải 2.
                    'remaining_formatted' => (new PriceValue(
                        (int) $next['remaining_minor'],
                        Currency::getDefault(),
                    ))->format(),
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

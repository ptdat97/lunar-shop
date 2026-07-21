<?php

namespace Modules\Promotion\Http\Controllers\Storefront;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Promotion\Services\PromotionService;

/**
 * Storefront promotion pages (SSR Blade). The index lists active promotions;
 * each promotion has its own page showing the products it covers. Data comes
 * from the shared PromotionService (no business logic in the controller/view).
 */
class PromotionPageController extends Controller
{
    public function __construct(protected PromotionService $promotions) {}

    /**
     * GET /promotions — all active displayable promotions, each with a few
     * preview products + a link to its detail page.
     */
    public function index(): View
    {
        $promotions = $this->promotions->displayablePromotions()->map(fn ($discount) => [
            'discount' => $discount,
            'description' => $this->promotions->describe($discount),
            'is_flash_sale' => (bool) (($discount->data ?? [])['flash_sale'] ?? false),
            'products' => $this->promotions->productsForPromotion($discount, 4),
        ]);

        return view('theme::pages.promotions', [
            'promotions' => $promotions,
        ]);
    }

    /**
     * GET /promotions/{handle} — one promotion and all its products.
     */
    public function show(string $handle): View
    {
        $discount = $this->promotions->findPromotion($handle);

        abort_if($discount === null, 404);

        return view('theme::pages.promotion', [
            'discount' => $discount,
            'description' => $this->promotions->describe($discount),
            'isFlashSale' => (bool) (($discount->data ?? [])['flash_sale'] ?? false),
            'products' => $this->promotions->productsForPromotion($discount, 60),
        ]);
    }
}

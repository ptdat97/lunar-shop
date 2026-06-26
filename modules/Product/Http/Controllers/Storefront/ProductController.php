<?php

namespace Modules\Product\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\SizeChartService;
use Modules\Recommend\Services\RecommendationService;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $sizeChart,
        protected RecommendationService $recommend,
    ) {}

    /**
     * Product detail page (SSR). Data from the same ProductService as the API.
     */
    public function show(string $slug): View
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        // A genuine product-page view (not every findBySlug) → drives
        // also-viewed recommendations and analytics.
        \Modules\Hook\Facades\Hook::doAction(
            \Modules\Hook\Support\Hooks::PRODUCT_VIEWED,
            [$product],
        );

        return view('theme::pages.product', [
            'product' => $product,
            // "You may also like" — curated associations first, collection fallback.
            'related' => $this->recommend->forProduct($product),
            'slug' => $slug,
            'sizeChart' => $this->sizeChart->for($product),
        ]);
    }
}

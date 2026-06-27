<?php

namespace Modules\Product\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\SizeChartService;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $sizeChart,
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
        Hook::doAction(Hooks::PRODUCT_VIEWED, [$product]);

        return view('theme::pages.product', [
            'product' => $product,
            'related' => $this->relatedFor($product),
            'slug' => $slug,
            'sizeChart' => $this->sizeChart->for($product),
        ]);
    }

    /**
     * "You may also like": the plain collection fallback, run through the
     * product.related filter so a recommender plugin (acme/recommend) can
     * prepend curated associations. With no such plugin, the fallback is used —
     * Product depends on no recommender.
     */
    protected function relatedFor(\Lunar\Models\Product $product, int $limit = 8)
    {
        return Hook::applyFilters(
            Hooks::PRODUCT_RELATED,
            $this->products->related($product, $limit),
            [$product, $limit],
        );
    }
}

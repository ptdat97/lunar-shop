<?php

namespace Modules\Catalog\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Catalog\Services\SizeChartService;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $sizeChart,
        protected RecommendationService $recommend,
    ) {}

    /**
     * Product detail page (SSR). Data from the same ProductService as the API;
     * Blade only receives computed values (standards §7 — no service resolution
     * in views).
     */
    public function show(Request $request, string $slug): View
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        // Deep-link: ?color=red&size=m preselects the matching variant so the
        // SSR page (no-JS visitors + crawlers) opens on the linked variant.
        // enhance/product-variant.js keeps this URL in sync client-side.
        $selectedVariant = $this->products->resolveSelectedVariant($product, $request->query());

        return view('theme::pages.product', [
            'product' => $product,
            'slug' => $slug,
            // Hydration payload for the variant enhancer — same ProductResource
            // shape as GET /api/v1/products/{slug} (SSR-first §8).
            'state' => ProductResource::make($product)->resolve(),
            'selectedVariant' => $selectedVariant,
            'selectedValues' => $this->products->selectedOptionValues($selectedVariant),
            'optionGroups' => $this->products->optionGroups($product),
            // "You may also like" — curated associations first, collection fallback.
            'related' => $this->recommend->forProduct($product),
            'sizeChart' => $this->sizeChart->for($product),
        ]);
    }
}

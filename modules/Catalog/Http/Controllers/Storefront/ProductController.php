<?php

namespace Modules\Catalog\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SizeChartService;
use Modules\Catalog\Services\RecommendationService;

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

        return view('theme::pages.product', [
            'product' => $product,
            // "You may also like" — curated associations first, collection fallback.
            'related' => $this->recommend->forProduct($product),
            'slug' => $slug,
            'sizeChart' => $this->sizeChart->for($product),
        ]);
    }
}

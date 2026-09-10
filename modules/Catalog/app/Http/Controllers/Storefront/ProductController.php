<?php

namespace Modules\Catalog\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Catalog\Services\ReviewService;
use Modules\Catalog\Services\SizeChartService;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $sizeChart,
        protected RecommendationService $recommend,
        protected ReviewService $reviews,
    ) {}

    /**
     * Product detail page (SSR). Data from the same ProductService as the API;
     * Blade only receives computed values (standards §7 — no service resolution
     * in views).
     */
    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $product = $this->products->findBySlug($slug);

        // A legacy/alias slug still routes to the live product — 301 to the
        // canonical URL (with deep-link query preserved) instead of 404ing the
        // old link. Lunar keeps old slugs in `lunar_urls` exactly for this.
        if ($product === null) {
            $canonical = $this->products->canonicalSlugFor($slug);

            if ($canonical !== null) {
                // Extra route params (anything not on the route) become the
                // query string, preserving ?color=…&size=… deep links.
                return redirect()->route(
                    'storefront.product',
                    ['slug' => $canonical] + $request->query->all(),
                    Response::HTTP_MOVED_PERMANENTLY,
                );
            }

            abort(404);
        }

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
            // SSR trước (§8): trang render sẵn đánh giá đã duyệt và tóm tắt sao,
            // JS chỉ lo gửi form. Trước đây đánh giá chỉ tồn tại ở API và màn
            // hình duyệt trong panel — storefront KHÔNG hiển thị ở đâu cả, nên
            // khách không có chỗ nào để đọc hay để viết.
            'reviews' => $this->reviews->forProduct($product->id, perPage: 10),
            'reviewSummary' => $this->reviews->summaryFor($product->id),
        ]);
    }
}

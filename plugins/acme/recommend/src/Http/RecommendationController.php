<?php

namespace Acme\Recommend\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cart\Services\CartService;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Product\Services\ProductService;
use Acme\Recommend\RecommendationService;

class RecommendationController extends Controller
{
    public function __construct(
        protected RecommendationService $recommend,
        protected ProductService $products,
        protected CartService $cart,
    ) {}

    /**
     * GET /api/v1/products/{slug}/recommendations
     * Recommendations for a product page. Same JSON shape as other product
     * listings (ProductResource), so the storefront card renderer reuses it.
     */
    public function forProduct(Request $request, string $slug)
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        $limit = min(24, max(1, (int) $request->input('limit', config('recommend.product_limit', 8))));

        $items = $this->recommend->forProduct($product, $limit);

        return ProductResource::collection($items);
    }

    /**
     * GET /api/v1/cart/recommendations
     * "You may also like" for the mini-cart, based on cart contents.
     */
    public function forCart(Request $request)
    {
        $limit = min(12, max(1, (int) $request->input('limit', config('recommend.cart_limit', 6))));

        $items = $this->recommend->forCart($this->cart->products(), $limit);

        return ProductResource::collection($items);
    }
}

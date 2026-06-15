<?php

namespace Modules\Product\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Product\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
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
            'related' => $this->products->related($product),
        ]);
    }
}

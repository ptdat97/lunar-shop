<?php

namespace Modules\Checkout\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Cart;
use Modules\Checkout\Http\Resources\CartResource;
use Modules\Checkout\Services\CartService;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
    ) {}

    /**
     * GET /api/v1/cart
     */
    public function show(): CartResource
    {
        return $this->resource($this->cart->current());
    }

    /**
     * POST /api/v1/cart  { sku_id, quantity }
     *
     * Accepts `variant_id` as a backward-compatible alias for `sku_id` so
     * existing headless clients keep working; both now carry a ProductSku id.
     */
    public function store(Request $request): CartResource
    {
        $data = $request->validate([
            'sku_id' => ['required_without:variant_id', 'integer'],
            'variant_id' => ['required_without:sku_id', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $skuId = $data['sku_id'] ?? $data['variant_id'];

        $cart = $this->cart->add($skuId, $data['quantity'] ?? 1);

        return $this->resource($cart);
    }

    /**
     * PATCH /api/v1/cart/lines/{line}  { quantity }
     */
    public function updateLine(Request $request, int $line): CartResource
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cart->updateLine($line, $data['quantity']);

        return $this->resource($cart);
    }

    /**
     * DELETE /api/v1/cart/lines/{line}
     */
    public function destroyLine(int $line): CartResource
    {
        $cart = $this->cart->remove($line);

        return $this->resource($cart);
    }

    /**
     * Wrap a cart in the stable JSON contract, with the relations
     * CartResource renders (single hydration point for every endpoint).
     */
    protected function resource(Cart $cart): CartResource
    {
        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }
}

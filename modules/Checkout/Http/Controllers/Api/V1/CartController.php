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
     * POST /api/v1/cart  { variant_id, quantity }
     */
    public function store(Request $request): CartResource
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cart->add($data['variant_id'], $data['quantity'] ?? 1);

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

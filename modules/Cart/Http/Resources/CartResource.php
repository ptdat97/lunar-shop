<?php

namespace Modules\Cart\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable JSON contract for the cart. Used by the cart drawer island,
 * checkout, and future app/headless clients.
 *
 * @mixin \Lunar\Models\Cart
 */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lines_count' => $this->lines->sum('quantity'),
            'lines' => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'quantity' => $line->quantity,
                'variant_id' => $line->purchasable_id,
                'name' => $line->purchasable?->product?->translateAttribute('name'),
                'slug' => $line->purchasable?->product?->defaultUrl?->slug,
                'sku' => $line->purchasable?->sku,
                'sub_total' => $line->subTotal?->formatted(),
            ])->values(),
            'totals' => [
                'sub_total' => $this->subTotal?->formatted(),
                'tax_total' => $this->taxTotal?->formatted(),
                'total' => $this->total?->formatted(),
            ],
        ];
    }
}

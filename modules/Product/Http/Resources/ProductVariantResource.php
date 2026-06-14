<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\Facades\Pricing;

/**
 * Variant JSON contract, including resolved price via Lunar's Pricing engine
 * (we inherit Lunar pricing — no reimplementation).
 *
 * @mixin \Lunar\Models\ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricing = Pricing::for($this->resource)->get();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'price' => [
                'amount' => $pricing->matched->price->decimal(),
                'formatted' => (string) $pricing->matched->price->formatted(),
            ],
        ];
    }
}

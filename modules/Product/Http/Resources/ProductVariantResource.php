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
            // Option values (e.g. Size: M, Color: Black) so the variant picker
            // can build its matrix from the shared payload — no extra fetch.
            'options' => $this->whenLoaded('values', fn () => $this->values->map(fn ($value) => [
                'option' => $value->option?->translate('name'),
                'value' => $value->translate('name'),
            ])->values()),
            // Per-variant gallery images (Lunar's media_product_variant pivot).
            // Empty when the admin hasn't assigned variant-specific media — the
            // gallery island then falls back to the product-level images.
            'images' => $this->whenLoaded('images', fn () => MediaImageResource::collection($this->images)),
        ];
    }
}

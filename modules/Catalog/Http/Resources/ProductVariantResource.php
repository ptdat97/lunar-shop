<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Services\PricingService;

/**
 * Variant JSON contract, including resolved price via the Pricing service
 * (wraps Lunar's Pricing engine — invoked in one place, no reimplementation).
 *
 * @mixin \Lunar\Models\ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = app(PricingService::class)->matchedPrice($this->resource);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'price' => [
                'amount' => $price?->decimal(),
                'formatted' => (string) $price?->formatted(),
                'currency' => $price?->currency?->code,
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

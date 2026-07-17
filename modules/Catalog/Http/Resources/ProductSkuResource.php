<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ProductSku;
use Modules\Catalog\Services\PricingService;

/**
 * SKU JSON contract, including resolved price via the Pricing service (wraps
 * Lunar's Pricing engine — invoked in one place, no reimplementation).
 *
 * A SKU is one Cartesian combination of the product's flexible `variables`;
 * `options` is the localised label list ("Size: M", "Color: Black") the variant
 * picker builds its matrix from, derived positionally from the parent product's
 * `variables` blob (no option/value tables to join).
 *
 * @mixin ProductSku
 */
class ProductSkuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = app(PricingService::class)->matchedPrice($this->resource);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'stock' => $this->quantity,
            'status' => $this->status,
            'price' => [
                'amount' => $price?->decimal(),
                'formatted' => (string) $price?->formatted(),
                'currency' => $price?->currency?->code,
            ],
            // Option name→value pairs (e.g. {option:"Color",value:"Black"}) so
            // the variant picker can build its matrix from the shared payload —
            // no extra fetch. This is the exact shape product-variant.js groups on.
            'options' => $this->optionPairs(),
            // Raw value-index combination into the product's `variables`, so a
            // client can map a picker selection back to this SKU without labels.
            'variant_indexes' => $this->variants ?? [],
            // Per-SKU images (the `images` JSON column). Empty when the admin
            // hasn't assigned SKU-specific media — the gallery island then falls
            // back to the product-level images.
            'images' => collect($this->images ?? [])->values(),
        ];
    }
}

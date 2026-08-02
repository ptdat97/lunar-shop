<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assets\Services\MediaUrl;
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
            // Public stock is the sellable figure. `quantity` is on-hand stock;
            // committed units are already sold and must not be offered again.
            'stock' => $this->getTotalInventory(),
            'on_hand' => $this->quantity,
            'committed' => (int) $this->committed,
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
            'variant_key' => $this->variantKey(),
            // Per-SKU images, resolved to the same {small,large,zoom,width,height}
            // shape as the product-level gallery so the storefront can swap one
            // for the other without knowing where the images came from. Empty
            // when the SKU has no own media — the gallery then falls back to the
            // product-level images.
            'images' => $this->galleryImages(),
        ];
    }

    /**
     * Resolve the SKU's `images` JSON column (a list of Media Library Asset ids,
     * picked via MediaPicker — modules/Assets) into the gallery image shape.
     *
     * The column holds Asset ids rather than URLs so conversions stay resolvable
     * after a library file is replaced, and the serialization lives in one place
     * (MediaImageResource), shared with the product-level gallery.
     *
     * Resolution goes through MediaUrl::assetMedia(), which memoizes per Asset
     * id on the scoped MediaUrl instance — a product page rendering dozens of
     * SKUs costs at most one query per distinct Asset id across the whole page.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function galleryImages(): array
    {
        $ids = collect($this->images ?? [])
            ->map(fn ($id) => is_array($id) ? ($id['id'] ?? null) : $id)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return [];
        }

        $mediaByAssetId = app(MediaUrl::class)->assetMedia($ids->all());

        // Preserve the admin's ordering: map over the ids, not the loaded rows.
        return $ids
            ->map(fn (int $id) => MediaImageResource::one($mediaByAssetId[$id] ?? null))
            ->filter()
            ->values()
            ->all();
    }
}

<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\Core\Models\ProductVariant;
use Modules\Assets\Services\MediaUrl;
use Modules\Catalog\Services\PricingService;
use Modules\Catalog\Support\VariantAxes;

/**
 * Variant JSON contract, including resolved price via the Pricing service
 * (wraps Lunar's Pricing engine — invoked in one place, no reimplementation).
 *
 * A variant is one combination of the product's option values; `options` is the
 * localised label list ("Size: M", "Color: Black") the picker builds its matrix
 * from, and `variant_indexes` is the positional encoding of that same
 * combination. The picker addresses variants positionally, so {@see VariantAxes}
 * derives the position from the product's option order rather than the payload
 * carrying option ids the client would have to understand.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = app(PricingService::class)->matchedPrice($this->resource);
        $axes = app(VariantAxes::class);
        $indexes = $axes->indexes($this->product, $this->resource);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            // Public stock is the sellable figure. `stock_on_hand` is what is in
            // the stockroom; committed units are already sold and must not be
            // offered again. Both are Lunar's rollups since the purchasable moved
            // to ProductVariant — derived from the order book, not counters this
            // application maintains.
            'stock' => $this->getTotalInventory(),
            'on_hand' => (int) $this->stock_on_hand,
            'committed' => (int) $this->stock_committed,
            'status' => $this->enabled ? 'published' : 'disabled',
            // `matchedPrice()` returns the Price MODEL since Lunar 2.0, so the
            // money helpers take the column name and are unit-quantity aware.
            'price' => [
                'amount' => $price?->unitDecimal('price'),
                'formatted' => (string) $price?->unitFormat('price'),
                'currency' => $price?->resolveCurrency()?->code,
            ],
            // Option name→value pairs (e.g. {option:"Color",value:"Black"}) so
            // the variant picker can build its matrix from the shared payload —
            // no extra fetch. This is the exact shape product-variant.js groups on.
            'options' => $axes->pairs($this->product, $this->resource),
            // Positional encoding of the same combination, so a client can map a
            // picker selection back to this variant without labels.
            'variant_indexes' => $indexes,
            'variant_key' => VariantAxes::key($indexes),
            // Per-SKU images, resolved to the same {small,large,zoom,width,height}
            // shape as the product-level gallery so the storefront can swap one
            // for the other without knowing where the images came from. Empty
            // when the variant has none — the gallery then falls back to the
            // product-level images.
            'images' => $this->galleryImages(),
        ];
    }

    /**
     * Resolve the variant's `images` JSON column (a list of Media Library Asset
     * ids picked from the shared library — modules/Assets) into the gallery
     * shape.
     *
     * The column holds Asset ids rather than URLs so conversions stay resolvable
     * after a library file is replaced, and the serialization lives in one place
     * (MediaImageResource), shared with the product-level gallery.
     *
     * Resolution goes through MediaUrl::assetMedia(), which memoizes per Asset
     * id on the scoped MediaUrl instance — a product page rendering dozens of
     * variants costs at most one query per distinct Asset id across the page.
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

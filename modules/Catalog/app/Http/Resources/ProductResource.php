<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Lunar\Models\Product;
use Modules\Assets\Services\MediaUrl;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\ReviewService;
use Modules\Inventory\Services\InventoryService;
use Modules\Promotion\Services\PromotionService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stable JSON contract for a product. Shared by web SSR, /api/v1, and
 * future app/headless clients. Keep the shape backwards-compatible.
 *
 * Optional extras (R3 — web↔API parity) are opt-in and only appear when set via
 * {@see self::withSizeChart()} / {@see self::withRelated()}, so the default shape
 * is unchanged. The single-call product endpoint uses `?include=size_chart,related`
 * to let a headless client fetch everything the web product page renders.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @var array<string, mixed>|null */
    protected ?array $sizeChart = null;

    /** @var Collection<int, Product>|null */
    protected $related = null;

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->translateAttribute('name'),
            'slug' => $this->defaultUrl?->slug,
            'description' => $this->translateAttribute('description'),
            'thumbnail' => $this->imageUrl($this->thumbnail, 'medium'),
            // Second image revealed on card hover — first gallery image that
            // isn't the primary thumbnail. Present only when media is loaded so
            // the JS-rendered grid matches the SSR card (product-card.blade.php).
            'hover_thumbnail' => $this->whenLoaded('media', fn () => $this->hoverThumbnailUrl()),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->name),
            // Flexible SKUs (one per variant combination). `variants` is kept as
            // a backward-compatible alias of the same payload for older clients.
            'skus' => ProductSkuResource::collection($this->whenLoaded('skus')),
            'variants' => ProductSkuResource::collection($this->whenLoaded('skus')),
            // The flexible variant definitions (Color/Size + values) so a client
            // can render the option picker without deriving it from the SKUs.
            'options' => app(ProductService::class)->optionGroups($this->resource),
            // Product-level gallery images — the default the storefront gallery
            // shows, and the fallback when a chosen SKU has no own images.
            'images' => $this->whenLoaded('media', fn () => MediaImageResource::collection($this->media)),
            // Opt-in extras (?include=…) — absent unless explicitly attached.
            'size_chart' => $this->when($this->sizeChart !== null, fn () => $this->sizeChart),
            'related' => $this->when($this->related !== null, fn () => static::collection($this->related)),
            // Cross-module enrichment (single store — direct, no hook layer):
            'availability' => app(InventoryService::class)->availabilityFor($this->resource),
            'promotion' => app(PromotionService::class)->saleFor($this->resource),
            'reviews' => app(ReviewService::class)->summaryFor($this->id),
        ];

        return $data;
    }

    /**
     * Attach the size chart payload so it serialises under `size_chart`.
     *
     * @param  array<string, mixed>  $sizeChart
     */
    public function withSizeChart(array $sizeChart): static
    {
        $this->sizeChart = $sizeChart;

        return $this;
    }

    /**
     * Attach related products so they serialise under `related`.
     *
     * @param  Collection<int, Product>  $related
     */
    public function withRelated($related): static
    {
        $this->related = $related;

        return $this;
    }

    /**
     * Resolve a conversion URL, generating the conversion on demand if its file
     * is missing (MediaUrl self-heals; falls back to the original if it can't).
     */
    protected function imageUrl(?Media $media, string $conversion): ?string
    {
        return app(MediaUrl::class)->conversion($media, $conversion);
    }

    /**
     * URL of the product's hover (second) image: the first gallery image that
     * isn't the primary thumbnail. Mirrors the SSR product-card composer so the
     * JS grid and Blade grid pick the same image. Null when there's only one.
     */
    protected function hoverThumbnailUrl(): ?string
    {
        $thumbId = $this->thumbnail?->id;
        $second = $this->media->first(fn ($m) => $m->id !== $thumbId);

        return $second ? $this->imageUrl($second, 'medium') : null;
    }
}

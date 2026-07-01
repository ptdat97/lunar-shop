<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
 * @mixin \Lunar\Models\Product
 */
class ProductResource extends JsonResource
{
    /** @var array<string, mixed>|null */
    protected ?array $sizeChart = null;

    /** @var \Illuminate\Support\Collection<int, \Lunar\Models\Product>|null */
    protected $related = null;

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->translateAttribute('name'),
            'slug' => $this->defaultUrl?->slug,
            'description' => $this->translateAttribute('description'),
            'thumbnail' => $this->imageUrl($this->thumbnail, 'medium'),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->name),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            // Product-level gallery images — the default the storefront gallery
            // shows, and the fallback when a chosen variant has no own images.
            'images' => $this->whenLoaded('media', fn () => MediaImageResource::collection($this->media)),
            // Opt-in extras (?include=…) — absent unless explicitly attached.
            'size_chart' => $this->when($this->sizeChart !== null, fn () => $this->sizeChart),
            'related' => $this->when($this->related !== null, fn () => static::collection($this->related)),
            // Cross-module enrichment (single store — direct, no hook layer):
            'availability' => app(\Modules\Inventory\Services\InventoryService::class)->availabilityFor($this->resource),
            'promotion' => app(\Modules\Promotion\Services\PromotionService::class)->saleFor($this->resource),
            'reviews' => app(\Modules\Review\Services\ReviewService::class)->summaryFor($this->id),
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
     * @param  \Illuminate\Support\Collection<int, \Lunar\Models\Product>  $related
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
        return app(\Modules\Assets\Services\MediaUrl::class)->conversion($media, $conversion);
    }
}

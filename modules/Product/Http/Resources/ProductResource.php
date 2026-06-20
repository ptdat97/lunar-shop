<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stable JSON contract for a product. Shared by web SSR, /api/v1, and
 * future app/headless clients. Keep the shape backwards-compatible.
 *
 * @mixin \Lunar\Models\Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
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
        ];
    }

    /**
     * Resolve a conversion URL, falling back to the original if the conversion
     * hasn't been generated yet (getUrl() throws otherwise).
     */
    protected function imageUrl(?Media $media, string $conversion): ?string
    {
        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
}

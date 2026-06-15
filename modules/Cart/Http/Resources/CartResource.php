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
                'thumbnail' => $this->lineThumbnail($line),
                'unit_price' => $line->unitPrice?->formatted(),
                'sub_total' => $line->subTotal?->formatted(),
            ])->values(),
            'coupon_code' => $this->coupon_code,
            'totals' => [
                'sub_total' => $this->subTotal?->formatted(),
                'discount_total' => $this->discountTotal?->formatted(),
                'tax_total' => $this->taxTotal?->formatted(),
                'total' => $this->total?->formatted(),
            ],
        ];
    }

    /**
     * Resolve a cart line's product thumbnail, falling back to the original
     * when the conversion isn't generated yet.
     */
    protected function lineThumbnail($line): ?string
    {
        $media = $line->purchasable?->product?->thumbnail;

        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion('small')
            ? $media->getUrl('small')
            : $media->getUrl();
    }
}


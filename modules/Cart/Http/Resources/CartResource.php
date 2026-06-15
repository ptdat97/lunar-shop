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
            'free_shipping' => $this->freeShippingInfo(),
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

    /**
     * Free-shipping progress info based on the configured threshold.
     *
     * @return array<string, mixed>|null  null when the threshold is disabled
     */
    protected function freeShippingInfo(): ?array
    {
        $threshold = (int) config('shipping.free_threshold', 0);

        if ($threshold <= 0) {
            return null;
        }

        $subTotal = $this->subTotal?->value ?? 0;
        $remaining = max(0, $threshold - $subTotal);
        $currency = $this->currency ?? \Lunar\Models\Currency::getDefault();

        return [
            'qualified' => $subTotal >= $threshold,
            'threshold' => (new \Lunar\DataTypes\Price($threshold, $currency))->formatted(),
            'remaining' => (new \Lunar\DataTypes\Price($remaining, $currency))->formatted(),
            'progress' => $threshold > 0 ? min(100, (int) round($subTotal / $threshold * 100)) : 0,
        ];
    }
}


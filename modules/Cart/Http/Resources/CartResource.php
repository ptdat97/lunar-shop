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
        $data = [
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
            // Promotions actually applied to this cart (flash sale, buy-2,
            // combo, coupon, membership) so the UI can label the savings.
            'applied_discounts' => $this->appliedDiscounts(),
            // Let a plugin add/adjust a total line (gift-wrap, surcharge) via the
            // cart.totals filter — CartResource stays unaware of those plugins.
            'totals' => \Modules\Platform\Facades\Hook::applyFilters(
                \Modules\Platform\Support\Hooks::CART_TOTALS,
                [
                    'sub_total' => $this->subTotal?->formatted(),
                    'discount_total' => $this->discountTotal?->formatted(),
                    // Raw minor-unit savings so the UI can decide whether to show a
                    // "you saved" row without parsing the formatted string.
                    'discount_value' => $this->discountTotal?->value ?? 0,
                    'tax_total' => $this->taxTotal?->formatted(),
                    'total' => $this->total?->formatted(),
                ],
                [$this->resource],
            ),
            'free_shipping' => $this->freeShippingInfo(),
        ];

        // Let other modules enrich the cart payload (e.g. Recommend adds
        // cross-sell suggestions) without this resource depending on them.
        return \Modules\Platform\Facades\Hook::applyFilters(
            \Modules\Platform\Support\Hooks::CART_RESOURCE,
            $data,
            [$this->resource],
        );
    }

    /**
     * Promotions applied to this cart, derived from Lunar's discount breakdown.
     * Each entry labels the discount + how much it saved, so the mini-cart /
     * cart page / checkout can show "Flash Sale −$5.00" style rows.
     *
     * @return array<int, array{name:string, description:string, amount:string, is_flash_sale:bool}>
     */
    protected function appliedDiscounts(): array
    {
        return app(\Modules\Promotion\Services\PromotionService::class)->appliedTo($this->resource);
    }

    /**
     * Resolve a cart line's product thumbnail, falling back to the original
     * when the conversion isn't generated yet.
     */
    protected function lineThumbnail($line): ?string
    {
        $media = $line->purchasable?->product?->thumbnail;

        // Generates the `small` conversion on demand if its file is missing.
        return app(\Modules\Media\Services\MediaUrl::class)->conversion($media, 'small');
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


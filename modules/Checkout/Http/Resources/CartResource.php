<?php

namespace Modules\Checkout\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Modules\Assets\Services\MediaUrl;
use Modules\Checkout\Services\TokenAwareCartSession;
use Modules\Core\Support\Settings;
use Modules\Promotion\Services\PromotionService;

/**
 * Stable JSON contract for the cart. Used by the cart drawer island,
 * checkout, and future app/headless clients.
 *
 * @mixin Cart
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
            'totals' => [
                'sub_total' => $this->subTotal?->formatted(),
                'discount_total' => $this->discountTotal?->formatted(),
                // Raw minor-unit savings so the UI can decide whether to show a
                // "you saved" row without parsing the formatted string.
                'discount_value' => $this->discountTotal?->value ?? 0,
                'tax_total' => $this->taxTotal?->formatted(),
                'total' => $this->total?->formatted(),
            ],
            'free_shipping' => $this->freeShippingInfo(),
        ];

        // Handle for stateless clients to send back as `X-Cart-Token`. Only ever
        // returned to the token client that owns the cart — never embedded in the
        // SSR storefront payload, where it would leak into the page HTML.
        if (TokenAwareCartSession::isStatelessRequest($request)) {
            $data['cart_token'] = $this->public_token;
        }

        return $data;
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
        return app(PromotionService::class)->appliedTo($this->resource);
    }

    /**
     * Resolve a cart line's product thumbnail, falling back to the original
     * when the conversion isn't generated yet.
     */
    protected function lineThumbnail($line): ?string
    {
        $media = $line->purchasable?->product?->thumbnail;

        // Generates the `small` conversion on demand if its file is missing.
        return app(MediaUrl::class)->conversion($media, 'small');
    }

    /**
     * Free-shipping progress info based on the configured threshold.
     *
     * @return array<string, mixed>|null null when the threshold is disabled
     */
    protected function freeShippingInfo(): ?array
    {
        $threshold = (int) app(Settings::class)->get('shipping.free_threshold', 0);

        if ($threshold <= 0) {
            return null;
        }

        $subTotal = $this->subTotal?->value ?? 0;
        $remaining = max(0, $threshold - $subTotal);
        $currency = $this->currency ?? Currency::getDefault();

        return [
            'qualified' => $subTotal >= $threshold,
            'threshold' => (new Price($threshold, $currency))->formatted(),
            'remaining' => (new Price($remaining, $currency))->formatted(),
            'progress' => $threshold > 0 ? min(100, (int) round($subTotal / $threshold * 100)) : 0,
        ];
    }
}

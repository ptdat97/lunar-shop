<?php

namespace Modules\Promotion\Support;

use Lunar\Models\Product;
use Modules\Hook\Facades\Hook;
use Modules\Hook\Support\Hooks;
use Modules\Promotion\Services\PromotionService;

/**
 * Registers Promotion's listeners on the shared hook bus. Enriches the product
 * API payload with a `promotion` block (badge + optional price break) so the
 * storefront JS-rendered grid and headless clients show the same sale info as
 * the SSR card — without ProductResource depending on this module.
 */
class PromotionHooks
{
    public static function register(): void
    {
        Hook::addFilter(
            Hooks::PRODUCT_RESOURCE,
            fn (array $data, Product $product): array => static::withPromotion($data, $product),
        );
    }

    /**
     * Add a `promotion` block mirroring {@see PromotionService::saleFor()}.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function withPromotion(array $data, Product $product): array
    {
        $data['promotion'] = app(PromotionService::class)->saleFor($product);

        return $data;
    }
}

<?php

namespace Modules\Inventory\Support;

use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Hook\Facades\Hook;
use Modules\Hook\Support\Hooks;
use Modules\Inventory\Services\InventoryService;

/**
 * Registers Inventory's listeners on the shared hook bus. Keeps the wiring in
 * one place (called from InventoryServiceProvider::boot) and out of the provider.
 */
class InventoryHooks
{
    public static function register(): void
    {
        // Oversell guard at add-to-cart time: veto when the variant can't
        // satisfy the requested quantity.
        Hook::addFilter(
            Hooks::PRODUCT_PURCHASABLE,
            fn (bool $purchasable, ProductVariant $variant, int $quantity): bool => $purchasable
                && app(InventoryService::class)->inStock($variant->id, $quantity),
        );

        // Enrich the product API payload with an `availability` block so the
        // storefront/headless client can show stock state — ProductResource
        // stays unaware of Inventory.
        Hook::addFilter(
            Hooks::PRODUCT_RESOURCE,
            fn (array $data, Product $product): array => static::withAvailability($data, $product),
        );
    }

    /**
     * Add `availability` (in_stock + total quantity) derived from the product's
     * variants. Sums tracked stock; an "always" variant makes the product
     * unconditionally in stock.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function withAvailability(array $data, Product $product): array
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        $inStock = $variants->contains(fn (ProductVariant $v) => $v->canBeFulfilledAtQuantity(1));
        $total = $variants->sum(fn (ProductVariant $v) => $v->purchasable === 'always'
            ? 0
            : max(0, (int) $v->getTotalInventory()));

        $data['availability'] = [
            'in_stock' => $inStock,
            'total_quantity' => $total,
        ];

        return $data;
    }
}

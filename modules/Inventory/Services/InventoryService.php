<?php

namespace Modules\Inventory\Services;

use Lunar\Models\ProductVariant;

class InventoryService
{
    /**
     * Get stock level for a variant.
     */
    public function stock(int $variantId): int
    {
        $variant = ProductVariant::find($variantId);

        return $variant?->stock ?? 0;
    }

    /**
     * Total inventory available to purchase, honouring the variant's
     * `purchasable` mode (in_stock = stock only; backorder = stock + backorder;
     * always = effectively unlimited). Delegates to Lunar's own accessor.
     */
    public function available(int $variantId): int
    {
        $variant = ProductVariant::find($variantId);

        if ($variant === null) {
            return 0;
        }

        return $variant->purchasable === 'always'
            ? PHP_INT_MAX
            : $variant->getTotalInventory();
    }

    /**
     * Check if a variant can be purchased at the requested quantity, honouring
     * backorder/always modes (not just raw stock). This is the oversell gate the
     * storefront should consult before adding to cart.
     */
    public function inStock(int $variantId, int $quantity = 1): bool
    {
        $variant = ProductVariant::find($variantId);

        return $variant?->canBeFulfilledAtQuantity($quantity) ?? false;
    }

    /**
     * Get low stock variants (below threshold).
     */
    public function lowStock(int $threshold = 5)
    {
        return ProductVariant::where('stock', '<', $threshold)
            ->where('stock', '>', 0)
            ->with('product')
            ->get();
    }

    /**
     * Get out of stock variants.
     */
    public function outOfStock()
    {
        return ProductVariant::where('stock', '<=', 0)
            ->with('product')
            ->get();
    }
}
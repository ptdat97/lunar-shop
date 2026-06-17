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
     * Check if a variant is in stock.
     */
    public function inStock(int $variantId, int $quantity = 1): bool
    {
        return $this->stock($variantId) >= $quantity;
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
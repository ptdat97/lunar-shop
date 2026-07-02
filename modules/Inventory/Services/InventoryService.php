<?php

namespace Modules\Inventory\Services;

use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class InventoryService
{
    /**
     * Availability summary for a product (used in the product API payload):
     * whether any variant is purchasable, and the total tracked stock. An
     * "always" variant makes the product unconditionally in stock.
     *
     * @return array{in_stock: bool, total_quantity: int}
     */
    public function availabilityFor(Product $product): array
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        return [
            'in_stock' => $variants->contains(fn (ProductVariant $v) => $v->canBeFulfilledAtQuantity(1)),
            'total_quantity' => (int) $variants->sum(fn (ProductVariant $v) => $v->purchasable === 'always'
                ? 0
                : max(0, (int) $v->getTotalInventory())),
        ];
    }

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
     * Whether a variant has physical stock on hand (stock > 0). This matches the
     * storefront's "in stock / Hết hàng" display (which is based on raw stock),
     * unlike inStock() which also returns true for backorder/always variants.
     * Use this for back-in-stock eligibility: a stock=0 "always" variant still
     * shows "Hết hàng", so the shopper should be allowed to subscribe.
     */
    public function hasPhysicalStock(int $variantId): bool
    {
        return $this->stock($variantId) > 0;
    }

    /** Default "low stock" threshold when the admin hasn't set one. */
    public const DEFAULT_LOW_THRESHOLD = 5;

    /**
     * Admin-configurable stock level at/below which a variant is "low".
     */
    public function lowStockThreshold(): int
    {
        return (int) app(\Modules\Core\Support\Settings::class)
            ->get('inventory.low_stock_threshold', self::DEFAULT_LOW_THRESHOLD);
    }

    /**
     * Get low stock variants (below the given threshold, or the configured one).
     */
    public function lowStock(?int $threshold = null)
    {
        $threshold ??= $this->lowStockThreshold();

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
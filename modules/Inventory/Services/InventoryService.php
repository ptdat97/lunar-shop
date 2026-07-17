<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;
use Modules\Core\Support\Settings;

class InventoryService
{
    /**
     * Availability summary for a product (used in the product API payload):
     * whether any SKU is purchasable, and the total tracked stock. SKUs track a
     * plain on-hand quantity (no backorder/always modes).
     *
     * @return array{in_stock: bool, total_quantity: int}
     */
    public function availabilityFor(Product $product): array
    {
        $skus = $product->relationLoaded('skus')
            ? $product->skus
            : $product->skus()->get();

        return [
            'in_stock' => $skus->contains(fn (ProductSku $s) => $s->canBeFulfilledAtQuantity(1)),
            'total_quantity' => (int) $skus->sum(fn (ProductSku $s) => max(0, (int) $s->getTotalInventory())),
        ];
    }

    /**
     * Get stock level for a SKU.
     */
    public function stock(int $skuId): int
    {
        $sku = ProductSku::find($skuId);

        return $sku?->quantity ?? 0;
    }

    /**
     * Total inventory available to purchase for a SKU (its on-hand quantity).
     */
    public function available(int $skuId): int
    {
        $sku = ProductSku::find($skuId);

        return $sku?->getTotalInventory() ?? 0;
    }

    /**
     * Check if a SKU can be purchased at the requested quantity. This is the
     * oversell gate the storefront should consult before adding to cart.
     */
    public function inStock(int $skuId, int $quantity = 1): bool
    {
        $sku = ProductSku::find($skuId);

        return $sku?->canBeFulfilledAtQuantity($quantity) ?? false;
    }

    /**
     * Whether a SKU has physical stock on hand (quantity > 0). Matches the
     * storefront's "in stock / Hết hàng" display and drives back-in-stock
     * eligibility (a stock=0 SKU should let a shopper subscribe).
     */
    public function hasPhysicalStock(int $skuId): bool
    {
        return $this->stock($skuId) > 0;
    }

    /** Default "low stock" threshold when the admin hasn't set one. */
    public const DEFAULT_LOW_THRESHOLD = 5;

    /**
     * Admin-configurable stock level at/below which a SKU is "low".
     */
    public function lowStockThreshold(): int
    {
        return (int) app(Settings::class)
            ->get('inventory.low_stock_threshold', self::DEFAULT_LOW_THRESHOLD);
    }

    /** How long an unpaid gateway order may hold its stock, by default. */
    public const DEFAULT_HOLD_MINUTES = 60;

    /** Lower bound: below this, a slow bank page would cancel live checkouts. */
    public const MIN_HOLD_MINUTES = 10;

    /** Upper bound (a week): past this the units are effectively lost anyway. */
    public const MAX_HOLD_MINUTES = 10080;

    /**
     * Minutes an unpaid gateway order keeps its reserved stock before
     * `orders:expire-abandoned` cancels it and returns the units.
     *
     * A shop decision, not a deployment one: during a sale you want the units
     * back in twenty minutes; on a normal week two hours is kinder to a shopper
     * fetching their card. Clamped, because a 0 here would cancel orders while
     * the shopper is still on the bank's page, and the admin gets no second
     * chance to notice — the stock is already gone.
     */
    public function holdMinutes(): int
    {
        $minutes = (int) app(Settings::class)
            ->get('inventory.hold_minutes', self::DEFAULT_HOLD_MINUTES);

        return max(self::MIN_HOLD_MINUTES, min(self::MAX_HOLD_MINUTES, $minutes));
    }

    /**
     * Get low stock SKUs (below the given threshold, or the configured one).
     */
    public function lowStock(?int $threshold = null)
    {
        $threshold ??= $this->lowStockThreshold();

        return ProductSku::where('quantity', '<', $threshold)
            ->where('quantity', '>', 0)
            ->with('product')
            ->get();
    }

    /**
     * Get out of stock SKUs.
     */
    public function outOfStock()
    {
        return ProductSku::where('quantity', '<=', 0)
            ->with('product')
            ->get();
    }

    // --- Overview stats (Stock Overview header) --------------------------------

    /** All SKUs track stock. */
    protected function tracked(): Builder
    {
        return ProductSku::query();
    }

    /** Count of tracked SKUs. */
    public function trackedCount(): int
    {
        return $this->tracked()->count();
    }

    /** Count of tracked SKUs at/below the low-stock threshold (but > 0). */
    public function lowCount(): int
    {
        return $this->tracked()->whereBetween('quantity', [1, $this->lowStockThreshold()])->count();
    }

    /** Count of tracked SKUs that are out of stock. */
    public function outCount(): int
    {
        return $this->tracked()->where('quantity', '<=', 0)->count();
    }

    /**
     * Total value of stock on hand, in minor units: SUM(quantity × cost_price)
     * over SKUs with stock. SKUs without a cost_price are skipped (COALESCE → 0
     * contribution), so this is the value of stock whose cost is known. Divide by
     * the default currency factor to display.
     */
    public function inventoryValueMinor(): int
    {
        return (int) $this->tracked()
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as value')
            ->value('value');
    }
}

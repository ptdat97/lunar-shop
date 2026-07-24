<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;
use Modules\Core\Support\Settings;
use Modules\Order\Support\OrderStatus;

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

    /** Orders paid this long ago but still undispatched are flagged as stale. */
    public const STALE_COMMITMENT_DAYS = 3;

    /**
     * Units physically in the stockroom, including those already sold but not
     * yet dispatched. This is the number a stock-take should match.
     */
    public function onHand(int $skuId): int
    {
        return $this->stock($skuId);
    }

    /**
     * Orders that are holding stock long after they were paid for.
     *
     * Committed units only return to the sellable pool when the order is
     * dispatched or cancelled. If the shop never marks an order dispatched, its
     * stock stays held forever and the shelf quietly stops selling — so surface
     * those orders instead of letting the commitment rot.
     *
     * @return Collection<int, Order>
     */
    public function staleCommitments(?int $days = null): Collection
    {
        $days ??= self::STALE_COMMITMENT_DAYS;

        return Order::query()
            ->whereNull('dispatched_at')
            ->whereNull('stock_released_at')
            ->whereNotNull('placed_at')
            ->whereIn('status', OrderStatus::paid())
            ->where('placed_at', '<=', now()->subDays($days))
            ->orderBy('placed_at')
            ->get();
    }

    /**
     * Units sold but not yet dispatched — reserved, still on the shelf.
     */
    public function committed(int $skuId): int
    {
        return (int) (ProductSku::find($skuId)?->committed ?? 0);
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
     * Whether a SKU can still be bought. Matches the storefront's
     * "in stock / Hết hàng" display and drives back-in-stock eligibility
     * (a sold-out SKU should let a shopper subscribe).
     *
     * Deliberately the SELLABLE figure, not the shelf count: units already
     * committed to another order are physically present but not for sale, and
     * showing them as available invites an oversell the guard then rejects at
     * checkout — the worst possible moment to find out.
     */
    public function hasPhysicalStock(int $skuId): bool
    {
        return $this->available($skuId) > 0;
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

        // Low on SELLABLE stock — that is what determines whether the shop can
        // keep taking orders. A SKU with a full shelf but everything committed
        // needs restocking just as urgently as an empty one.
        return ProductSku::whereRaw('quantity - committed < ?', [$threshold])
            ->whereRaw('quantity - committed > 0')
            ->with('product')
            ->get();
    }

    /**
     * Get out of stock SKUs.
     */
    public function outOfStock()
    {
        return ProductSku::whereRaw('quantity - committed <= 0')
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

    /**
     * Count of tracked SKUs low on SELLABLE stock (but not yet at zero).
     *
     * Counted on `quantity - committed` for the same reason the table filters
     * are: units promised to an unshipped order cannot fill the next order, so
     * they must not make a SKU look healthier than it is.
     */
    public function lowCount(): int
    {
        return $this->tracked()
            ->whereRaw('quantity - committed BETWEEN 1 AND ?', [$this->lowStockThreshold()])
            ->count();
    }

    /** Count of tracked SKUs with nothing left to sell. */
    public function outCount(): int
    {
        return $this->tracked()->whereRaw('quantity - committed <= 0')->count();
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

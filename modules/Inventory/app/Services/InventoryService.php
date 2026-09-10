<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Modules\Core\Support\Settings;
use Modules\Order\Support\OrderStatus;

class InventoryService
{
    /**
     * Availability summary for a product (used in the product API payload):
     * whether any variant is purchasable, and the total tracked stock.
     *
     * @return array{in_stock: bool, total_quantity: int}
     */
    public function availabilityFor(Product $product): array
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        return [
            'in_stock' => $variants->contains(fn (ProductVariant $s) => $s->canBeFulfilledAtQuantity(1)),
            'total_quantity' => (int) $variants->sum(fn (ProductVariant $s) => max(0, (int) $s->getTotalInventory())),
        ];
    }

    /**
     * Get on-hand stock for a variant.
     */
    public function stock(int $variantId): int
    {
        $variant = ProductVariant::find($variantId);

        return (int) ($variant?->stock_on_hand ?? 0);
    }

    /** Orders paid this long ago but still undispatched are flagged as stale. */
    public const STALE_COMMITMENT_DAYS = 3;

    /**
     * Units physically in the stockroom, including those already sold but not
     * yet dispatched. This is the number a stock-take should match.
     */
    public function onHand(int $variantId): int
    {
        return $this->stock($variantId);
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

        return OrderStatus::scopePaid(Order::query())
            // "Not dispatched" is the fulfilment rollup now, not an order
            // column: an order can ship in several parcels, which one
            // `dispatched_at` timestamp could never describe honestly.
            ->whereNotIn('fulfilment_status', ['fulfilled', 'returned'])
            ->whereNull('stock_released_at')
            ->where('placed_at', '<=', now()->subDays($days))
            ->orderBy('placed_at')
            ->get();
    }

    /**
     * Units sold but not yet dispatched — reserved, still on the shelf.
     */
    public function committed(int $variantId): int
    {
        return (int) (ProductVariant::find($variantId)?->stock_committed ?? 0);
    }

    /**
     * Total inventory available to purchase for a variant.
     */
    public function available(int $variantId): int
    {
        $variant = ProductVariant::find($variantId);

        return $variant?->getTotalInventory() ?? 0;
    }

    /**
     * Check if a variant can be purchased at the requested quantity. This is the
     * oversell gate the storefront should consult before adding to cart.
     */
    public function inStock(int $variantId, int $quantity = 1): bool
    {
        $variant = ProductVariant::find($variantId);

        return $variant?->canBeFulfilledAtQuantity($quantity) ?? false;
    }

    /**
     * Whether a variant can still be bought. Matches the storefront's
     * "in stock / Hết hàng" display and drives back-in-stock eligibility
     * (a sold-out variant should let a shopper subscribe).
     *
     * Deliberately the SELLABLE figure, not the shelf count: units already
     * committed to another order are physically present but not for sale, and
     * showing them as available invites an oversell the guard then rejects at
     * checkout — the worst possible moment to find out.
     */
    public function hasPhysicalStock(int $variantId): bool
    {
        return $this->available($variantId) > 0;
    }

    /** Default "low stock" threshold when the admin hasn't set one. */
    public const DEFAULT_LOW_THRESHOLD = 5;

    /**
     * Admin-configurable stock level at/below which a variant is "low".
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

    // --- Overview stats (Stock Overview header) --------------------------------

}

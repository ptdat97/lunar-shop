<?php

namespace Modules\Inventory\Services;

use Lunar\Models\ProductVariant;
use Modules\Inventory\Models\StockNotification;

/**
 * Manages back-in-stock ("notify me") subscriptions for out-of-stock variants.
 */
class StockNotificationService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * Subscribe an email to a variant that is currently out of stock.
     *
     * Owns the whole rule, including "is it actually out of stock?" — that used
     * to sit in the controller, where nothing stopped a second caller (an admin
     * action, a job) from subscribing someone to an in-stock variant.
     *
     * Deliberately *not* `inStock()` / `canBeFulfilledAtQuantity()`: a `stock=0`
     * backorder/always variant still reads as out of stock on the storefront, so
     * the shopper must be able to subscribe to it.
     *
     * Idempotent: re-subscribing the same email to the same variant returns the
     * existing pending row (and resets it to pending if it was already notified
     * but has since sold out again).
     */
    public function subscribe(int $variantId, string $email): StockNotification
    {
        $variant = ProductVariant::findOrFail($variantId);

        abort_if(
            $this->inventory->hasPhysicalStock($variant->id),
            422,
            'This item is already in stock.',
        );

        $email = mb_strtolower(trim($email));

        return StockNotification::updateOrCreate(
            ['product_variant_id' => $variant->id, 'email' => $email],
            ['notified_at' => null],
        );
    }

    /**
     * Number of shoppers waiting on a variant.
     */
    public function pendingCount(ProductVariant $variant): int
    {
        return StockNotification::query()
            ->where('product_variant_id', $variant->id)
            ->pending()
            ->count();
    }
}

<?php

namespace Modules\Inventory\Services;

use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Models\StockNotification;

/**
 * Manages back-in-stock ("notify me") subscriptions for out-of-stock SKUs.
 */
class StockNotificationService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * Subscribe an email to a SKU that is currently out of stock.
     *
     * Owns the whole rule, including "is it actually out of stock?" — that used
     * to sit in the controller, where nothing stopped a second caller (an admin
     * action, a job) from subscribing someone to an in-stock SKU.
     *
     * Idempotent: re-subscribing the same email to the same SKU returns the
     * existing pending row (and resets it to pending if it was already notified
     * but has since sold out again).
     */
    public function subscribe(int $skuId, string $email): StockNotification
    {
        $sku = ProductSku::findOrFail($skuId);

        abort_if(
            $this->inventory->hasPhysicalStock($sku->id),
            422,
            'This item is already in stock.',
        );

        $email = mb_strtolower(trim($email));

        return StockNotification::updateOrCreate(
            ['product_sku_id' => $sku->id, 'email' => $email],
            ['notified_at' => null],
        );
    }

    /**
     * Number of shoppers waiting on a SKU.
     */
    public function pendingCount(ProductSku $sku): int
    {
        return StockNotification::query()
            ->where('product_sku_id', $sku->id)
            ->pending()
            ->count();
    }
}

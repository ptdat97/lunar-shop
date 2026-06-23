<?php

namespace Modules\Inventory\Services;

use Lunar\Models\ProductVariant;
use Modules\Inventory\Models\StockNotification;

/**
 * Manages back-in-stock ("notify me") subscriptions for out-of-stock variants.
 */
class StockNotificationService
{
    /**
     * Subscribe an email to be notified when a variant is restocked.
     *
     * Idempotent: re-subscribing the same email to the same variant returns the
     * existing pending row (and resets it to pending if it was already notified
     * but has since sold out again).
     */
    public function subscribe(ProductVariant $variant, string $email): StockNotification
    {
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

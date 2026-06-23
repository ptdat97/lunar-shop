<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\Mail;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Mail\BackInStockMail;
use Modules\Inventory\Models\StockNotification;

/**
 * Emails pending "notify me" subscribers when a variant is restocked, then marks
 * their subscriptions notified so they aren't emailed again on the next restock.
 *
 * Driven by ProductVariantObserver (fires when stock crosses ≤0 → >0). Mail is
 * queued (BackInStockMail implements ShouldQueue), so this stays cheap on the
 * web request / admin save that replenished stock.
 */
class BackInStockNotifier
{
    public function notify(ProductVariant $variant): int
    {
        $subscriptions = StockNotification::query()
            ->where('product_variant_id', $variant->id)
            ->pending()
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $variant->loadMissing('product');
        $productName = $variant->getDescription();
        $url = $this->productUrl($variant);

        foreach ($subscriptions as $subscription) {
            Mail::to($subscription->email)->send(
                new BackInStockMail($variant, $productName, $url)
            );
        }

        // Single UPDATE rather than per-row saves.
        StockNotification::query()
            ->whereKey($subscriptions->modelKeys())
            ->update(['notified_at' => now()]);

        return $subscriptions->count();
    }

    protected function productUrl(ProductVariant $variant): ?string
    {
        $slug = $variant->product?->defaultUrl?->slug;

        return $slug ? url("/products/{$slug}") : null;
    }
}

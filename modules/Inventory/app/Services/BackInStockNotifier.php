<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\Mail;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Mail\BackInStockMail;
use Modules\Inventory\Models\StockNotification;

/**
 * Emails pending "notify me" subscribers when a SKU is restocked, then marks
 * their subscriptions notified so they aren't emailed again on the next restock.
 *
 * Driven by ProductSkuObserver (fires when stock crosses ≤0 → >0). Mail is
 * queued (BackInStockMail implements ShouldQueue), so this stays cheap on the
 * web request / admin save that replenished stock.
 */
class BackInStockNotifier
{
    public function notify(ProductSku $sku): int
    {
        $subscriptions = StockNotification::query()
            ->where('product_sku_id', $sku->id)
            ->pending()
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $sku->loadMissing('product');
        $productName = $sku->getDescription();
        $url = $this->productUrl($sku);

        foreach ($subscriptions as $subscription) {
            Mail::to($subscription->email)->send(
                new BackInStockMail($sku, $productName, $url)
            );
        }

        // Single UPDATE rather than per-row saves.
        StockNotification::query()
            ->whereKey($subscriptions->modelKeys())
            ->update(['notified_at' => now()]);

        return $subscriptions->count();
    }

    protected function productUrl(ProductSku $sku): ?string
    {
        $slug = $sku->product?->defaultUrl?->slug;

        return $slug ? url("/products/{$slug}") : null;
    }
}

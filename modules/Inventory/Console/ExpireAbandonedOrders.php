<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Lunar\Models\Order;
use Modules\Order\Support\OrderStatus;

/**
 * Cancels orders that were created for a gateway payment which never arrived,
 * returning their reserved stock.
 *
 * The order row (and its stock reservation) is created *before* the customer is
 * redirected to VNPay/MoMo. A shopper who closes the tab leaves the units held
 * forever — measured: stock 5 → 3 with nobody having paid a thing.
 *
 * Cancelling flips the status, and ReleaseStockOnOrderClosed puts the units back;
 * the release is idempotent, so a rerun cannot inflate inventory.
 */
class ExpireAbandonedOrders extends Command
{
    protected $signature = 'orders:expire-abandoned
                            {--minutes=60 : How long an unpaid order may hold stock}
                            {--dry-run : Report what would be cancelled}';

    protected $description = 'Cancel unpaid gateway orders and return their reserved stock';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);
        $dryRun = (bool) $this->option('dry-run');

        // Only *gateway* orders expire. `awaiting-payment` is also the status a
        // bank transfer sits in while staff wait for the money to land — those
        // are settled by hand and must never be cancelled by a timer. VNPay and
        // MoMo stamp `meta.payment_type`; Lunar's offline driver stamps nothing,
        // which is exactly the distinction we need.
        $gateways = array_keys(array_filter(
            (array) config('lunar.payments.types', []),
            fn ($type) => ($type['driver'] ?? 'offline') !== 'offline',
        ));

        if ($gateways === []) {
            $this->info('No gateway payment types configured; nothing can be abandoned.');

            return self::SUCCESS;
        }

        $query = Order::query()
            ->where('status', OrderStatus::AWAITING_PAYMENT)
            ->whereNull('stock_released_at')
            ->where('created_at', '<', $cutoff)
            ->whereIn('meta->payment_type', $gateways);

        $count = 0;

        $query->chunkById(100, function ($orders) use ($dryRun, &$count) {
            foreach ($orders as $order) {
                $count++;
                $this->line("  #{$order->id} {$order->reference} (created {$order->created_at})");

                if (! $dryRun) {
                    // The status change releases the stock via the domain event.
                    $order->update(['status' => OrderStatus::CANCELLED]);
                }
            }
        });

        $this->info($dryRun
            ? "Dry run: {$count} abandoned order(s) would be cancelled."
            : "Cancelled {$count} abandoned order(s); their stock is back.");

        return self::SUCCESS;
    }
}

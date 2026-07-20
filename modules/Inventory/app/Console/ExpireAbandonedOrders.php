<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Lunar\Models\Order;
use Modules\Inventory\Services\InventoryService;
use Modules\Order\Support\OrderStatus;

/**
 * Cancels orders that hold reserved stock but will never be paid for, returning
 * their units. Two kinds qualify:
 *
 * 1. **Abandoned gateway orders.** The order row (and its stock reservation) is
 *    created *before* the customer is redirected to VNPay/MoMo. A shopper who
 *    closes the tab leaves the units held forever — measured: stock 5 → 3 with
 *    nobody having paid a thing.
 * 2. **Orphaned drafts.** Lunar's `CreateOrder` commits its transaction (stock
 *    reserved) and the payment driver *then* stamps `placed_at` + `meta` in a
 *    separate statement. A process that dies in between leaves an order with
 *    neither — invisible to (1), which matches on `meta.payment_type`.
 *
 * Bank transfers are deliberately excluded: they legitimately sit in
 * `awaiting-payment` for days and are settled by hand.
 *
 * Cancelling flips the status, and ReleaseStockOnOrderClosed puts the units back;
 * the release is idempotent, so a rerun cannot inflate inventory.
 */
class ExpireAbandonedOrders extends Command
{
    protected $signature = 'orders:expire-abandoned
                            {--minutes= : Override how long an unpaid order may hold stock}
                            {--dry-run : Report what would be cancelled}';

    protected $description = 'Cancel unpaid gateway orders and return their reserved stock';

    public function handle(InventoryService $inventory): int
    {
        // No flag → the shop's own setting (Admin → Inventory). The flag stays for
        // one-off sweeps ("clear anything older than 5 minutes, we just fixed the
        // gateway") without touching what the scheduler does every ten minutes.
        $minutes = $this->option('minutes') !== null
            ? max(1, (int) $this->option('minutes'))
            : $inventory->holdMinutes();

        $cutoff = now()->subMinutes($minutes);
        $dryRun = (bool) $this->option('dry-run');

        // `awaiting-payment` is also the status a bank transfer sits in while staff
        // wait for the money to land — those are settled by hand and must never be
        // cancelled by a timer. VNPay and MoMo stamp `meta.payment_type`; Lunar's
        // offline driver stamps nothing, which is exactly the distinction we need.
        $gateways = array_keys(array_filter(
            (array) config('lunar.payments.types', []),
            fn ($type) => ($type['driver'] ?? 'offline') !== 'offline',
        ));

        $query = Order::query()
            ->whereNull('stock_released_at')
            ->where('created_at', '<', $cutoff)
            ->where(function ($q) use ($gateways) {
                // (a) A gateway order the shopper never paid for. With no gateway
                // configured this branch matches nothing — but (b) must still run.
                if ($gateways !== []) {
                    $q->where(fn ($q) => $q
                        ->where('status', OrderStatus::AWAITING_PAYMENT)
                        ->whereIn('meta->payment_type', $gateways)
                    );
                }

                // (b) An orphan: `CreateOrder` committed (reserving stock) but the
                // driver died before stamping `placed_at`/`meta`. Every driver sets
                // `placed_at` the instant authorize() succeeds, so its absence means
                // checkout never finished — and with no `meta.payment_type`, branch
                // (a) can never see it. Such an order held its units forever.
                $q->orWhereNull('placed_at');
            });

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

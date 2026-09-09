<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Support\OrderStatus;

/**
 * Give every historical order a `meta.payment_type`.
 *
 * Lunar 2.0 removed `lunar_orders.status`, so the shop's lifecycle is derived
 * from the transaction ledger, the fulfilments and the cancelled/closed
 * timestamps ({@see OrderStatus}). Exactly one
 * distinction those facts cannot make on their own is `payment-offline` (cash
 * on delivery — a real sale the moment it is placed) versus `awaiting-payment`
 * (a gateway the shopper abandoned, or a bank transfer still in the post). Both
 * are payment-pending; what separates them is how the order was meant to be
 * paid, which lives in `meta.payment_type`.
 *
 * Checkout now records it for every order. Orders placed before that only have
 * one if they went through a gateway — the VNPay and MoMo drivers always wrote
 * theirs, while the offline driver wrote nothing at all. So an older placed
 * order with no payment type is provably one of the offline methods, and this
 * shop's offline default is COD.
 *
 * Without this, every historical COD order would read as `awaiting-payment` and
 * silently drop out of revenue, lifetime spend, co-purchase recommendations and
 * fit history — the four things that ask "was this a real sale?".
 *
 * Only rows that were actually placed are touched: an unplaced draft never
 * chose a payment method, and `ExpireAbandonedOrders` relies on `placed_at`
 * being absent to spot orphans.
 */
return new class extends Migration
{
    /** The offline method this storefront defaults to. */
    private const FALLBACK = 'cod';

    private function table(): string
    {
        return config('lunar.database.table_prefix').'orders';
    }

    public function up(): void
    {
        if (! Schema::hasTable($this->table()) || ! Schema::hasColumn($this->table(), 'meta')) {
            return;
        }

        DB::table($this->table())
            ->whereNotNull('placed_at')
            ->orderBy('id')
            ->each(function ($row) {
                $meta = json_decode($row->meta ?? 'null', true);
                $meta = is_array($meta) ? $meta : [];

                if (filled($meta['payment_type'] ?? null)) {
                    return;
                }

                $meta['payment_type'] = self::FALLBACK;

                DB::table($this->table())
                    ->where('id', $row->id)
                    ->update(['meta' => json_encode($meta)]);
            });
    }

    /**
     * Deliberately not reversible. Removing the key again would leave those
     * orders unclassifiable rather than restoring information — the old
     * `status` column they came from no longer exists.
     */
    public function down(): void {}
};

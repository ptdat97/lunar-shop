<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `lunar_orders.dispatched_at`.
 *
 * It was the idempotency marker for this shop's own dispatch settlement — the
 * thing that stopped a second write to status 'dispatched' decrementing stock
 * twice. That mechanism (`SettleStockOnDispatch`) went away with the move to
 * Lunar 2.0, which drives stock from its own fulfilment events and holds the
 * answer in `fulfilment_status`.
 *
 * Neither Lunar's core nor its panel references the column, and the only
 * mention left in this codebase is a comment explaining why a single dispatch
 * timestamp could never describe an order that ships in several parcels.
 *
 * `stock_released_at` next to it is NOT dropped: it is still this shop's own
 * marker, written by StampStockReleased and read by OrderStatus.
 */
return new class extends Migration
{
    private function table(): string
    {
        return config('lunar.database.table_prefix').'orders';
    }

    public function up(): void
    {
        if (! Schema::hasColumn($this->table(), 'dispatched_at')) {
            return;
        }

        Schema::table($this->table(), function (Blueprint $table): void {
            $table->dropColumn('dispatched_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn($this->table(), 'dispatched_at')) {
            return;
        }

        Schema::table($this->table(), function (Blueprint $table): void {
            $table->timestamp('dispatched_at')->nullable()->after('stock_released_at');
        });
    }
};

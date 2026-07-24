<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split "on the shelf" from "already sold" (Bagisto's ordered_inventories idea,
 * adapted to a single-store shop as one column rather than a table).
 *
 * Before this, placing an order decremented `quantity` immediately, so the one
 * number answered neither question honestly:
 *   - "how many are physically in the stockroom?"  → understated by open orders
 *   - "how many can I still sell?"                 → correct, by accident
 *
 * After: `quantity` stays put when an order is placed and `committed` rises.
 * Units leave `quantity` when the order is dispatched. Sellable is the
 * derived figure `quantity - committed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('lunar.database.table_prefix').'product_skus';
        $orders = config('lunar.database.table_prefix').'orders';

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unsignedInteger('committed')
                ->default(0)
                ->after('quantity')
                ->comment('Units sold but not yet dispatched; sellable = quantity - committed');
        });

        // When an order's units physically left the shelf. Idempotency marker
        // for the dispatch settlement, mirroring `stock_released_at` — without
        // it a second status write to `dispatched` would decrement twice.
        Schema::table($orders, function (Blueprint $blueprint) {
            $blueprint->timestamp('dispatched_at')->nullable()->after('stock_released_at');
        });

        // Backfill: any order that has decremented stock and not been released
        // is still holding units. Those units already left `quantity` under the
        // old model, so add them back and record them as committed instead —
        // otherwise the shelf count stays understated for every open order.
        $prefix = config('lunar.database.table_prefix');

        $open = DB::table($prefix.'order_lines')
            ->join($prefix.'orders', $prefix.'orders.id', '=', $prefix.'order_lines.order_id')
            ->whereNull($prefix.'orders.stock_released_at')
            ->whereNull($prefix.'orders.dispatched_at')
            ->where($prefix.'order_lines.purchasable_type', 'product_sku')
            ->select($prefix.'order_lines.purchasable_id as sku_id')
            ->selectRaw('SUM('.$prefix.'order_lines.quantity) as units')
            ->groupBy($prefix.'order_lines.purchasable_id')
            ->get();

        foreach ($open as $row) {
            DB::table($table)
                ->where('id', $row->sku_id)
                ->update([
                    'quantity' => DB::raw('quantity + '.(int) $row->units),
                    'committed' => (int) $row->units,
                ]);
        }
    }

    public function down(): void
    {
        $table = config('lunar.database.table_prefix').'product_skus';

        // Collapse back to the old model so the column can go: committed units
        // were never taken out of `quantity`, so take them out now.
        DB::table($table)->where('committed', '>', 0)->update([
            'quantity' => DB::raw('GREATEST(quantity - committed, 0)'),
        ]);

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('committed');
        });

        Schema::table(config('lunar.database.table_prefix').'orders', function (Blueprint $blueprint) {
            $blueprint->dropColumn('dispatched_at');
        });
    }
};

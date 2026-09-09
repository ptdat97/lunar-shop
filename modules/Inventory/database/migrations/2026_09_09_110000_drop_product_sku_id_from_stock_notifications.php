<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire `stock_notifications.product_sku_id`.
 *
 * The SKU → variant migration adds `product_variant_id` and backfills it, but
 * leaves the old column in place so the move is reversible up to that point.
 * It is `NOT NULL`, so every new subscription fails until it goes.
 *
 * Separate from that migration on purpose: dropping a column is the one step
 * that cannot be undone from the data still present, so it lands only after the
 * new column has been written and read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_notifications') || ! Schema::hasColumn('stock_notifications', 'product_sku_id')) {
            return;
        }

        Schema::table('stock_notifications', function (Blueprint $table) {
            // The foreign key has to go first — MySQL refuses to drop a column
            // an index still references.
            $table->dropForeign(['product_sku_id']);
            $table->dropColumn('product_sku_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_notifications') || Schema::hasColumn('stock_notifications', 'product_sku_id')) {
            return;
        }

        Schema::table('stock_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('product_sku_id')->nullable()->index();
        });
    }
};

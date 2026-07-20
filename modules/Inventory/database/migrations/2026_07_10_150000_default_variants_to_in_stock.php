<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make "out of stock" actually mean out of stock.
 *
 * `lunar_product_variants.purchasable` defaults to `always` in Lunar's own
 * migration — a mode that means "sell it whether or not we hold any". Nothing in
 * this codebase ever set it, so every variant was a backorder variant, and the
 * oversell guards in DecrementStock and CartService (both of which correctly
 * exempt `backorder`/`always`) never fired.
 *
 * Measured before this change: stock 2, order 10 → checkout 200 OK, stock −8.
 *
 * A single-store fashion shop sells what it has. Admins can still switch an
 * individual variant to `backorder` or `always` in the product editor when they
 * genuinely want to sell ahead of delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Existing rows: every one of them is on Lunar's untouched default, so
        // none of them expresses a deliberate backorder decision.
        DB::table('lunar_product_variants')
            ->where('purchasable', 'always')
            ->update(['purchasable' => 'in_stock']);

        // New rows default to the same. `change()` needs doctrine/dbal on some
        // stacks, so alter the column directly.
        DB::statement("ALTER TABLE lunar_product_variants MODIFY purchasable VARCHAR(255) NOT NULL DEFAULT 'in_stock'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lunar_product_variants MODIFY purchasable VARCHAR(255) NOT NULL DEFAULT 'always'");

        DB::table('lunar_product_variants')
            ->where('purchasable', 'in_stock')
            ->update(['purchasable' => 'always']);
    }
};

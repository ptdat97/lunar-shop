<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the last remains of the pre-2.0 purchasable.
 *
 * Three tables from that era go together:
 *
 *  - `lunar_product_skus` — this shop's own purchasable before the convergence
 *    onto Lunar's `ProductVariant`.
 *  - `sku_variant_map` — the bridge that let an old row be traced to its new
 *    one. Kept as a safety net: the only way to answer "which SKU did this old
 *    order point at" if something surfaced late.
 *  - `stock_movements` — the shop's own stock ledger. Lunar 2.0 owns this now
 *    (`lunar_stock_movements`, written by its own AdjustStock/RecordStockMovement
 *    actions), and the model and enum that read the old one were deleted with
 *    the convergence. It is only still here because a foreign key tied it to
 *    the SKU table.
 *
 * Nothing outside migrations has read any of them since the convergence, and
 * the mapping was verified whole (648 SKUs ↔ 648 map rows ↔ 648 variants, no
 * orphans either way) before this was written.
 *
 * This is the one step that cannot be undone from the data that remains: `down()`
 * can recreate the shape but never the rows. See
 * docs/guides/migrate-skus-to-variants.md §7.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Children first: both reference the SKU table by foreign key, and
        // MySQL refuses to drop a table something still points at.
        Schema::dropIfExists('sku_variant_map');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists(config('lunar.database.table_prefix').'product_skus');
    }

    public function down(): void
    {
        // Deliberately empty. Recreating two empty tables would be a lie: the
        // rows are gone, and code that needed them would fail more confusingly
        // against an empty table than against a missing one.
    }
};

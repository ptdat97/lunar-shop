<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the variants that predate the SKU → variant move.
 *
 * Before the move the shop sold `ProductSku` and Lunar's `ProductVariant` sat
 * unused, except that the demo seeders created one per product anyway. Harmless
 * while nothing read them; not harmless now, because the same products own both
 * the migrated variants (the real ones) and these — so a product page would
 * offer a variant with no place on any axis, and the panel would count stock
 * twice.
 *
 * Identified by absence from `sku_variant_map`: everything this shop sells came
 * through that map. Only runs where the map exists, so it cannot fire on a
 * database that never had SKUs.
 *
 * Their prices and stock levels go with them — nothing else referenced these
 * rows (0 order lines, 0 cart lines, verified before deleting).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');
        $variants = $prefix.'product_variants';

        if (! Schema::hasTable('sku_variant_map') || ! Schema::hasTable($variants)) {
            return;
        }

        $legacy = DB::table($variants)
            ->whereNotIn('id', fn ($query) => $query->select('product_variant_id')->from('sku_variant_map'))
            ->pluck('id');

        if ($legacy->isEmpty()) {
            return;
        }

        // Refuse to delete anything a customer actually bought or is holding.
        $referenced = DB::table($prefix.'order_lines')
            ->where('purchasable_type', 'product_variant')
            ->whereIn('purchasable_id', $legacy)
            ->union(
                DB::table($prefix.'cart_lines')
                    ->where('purchasable_type', 'product_variant')
                    ->whereIn('purchasable_id', $legacy)
                    ->select('purchasable_id')
            )
            ->pluck('purchasable_id');

        $deletable = $legacy->diff($referenced);

        if ($deletable->isEmpty()) {
            return;
        }

        DB::table($prefix.'prices')
            ->where('priceable_type', 'product_variant')
            ->whereIn('priceable_id', $deletable)
            ->delete();

        DB::table($prefix.'stock_levels')->whereIn('product_variant_id', $deletable)->delete();
        DB::table($prefix.'stock_movements')->whereIn('product_variant_id', $deletable)->delete();
        DB::table($prefix.'product_option_value_product_variant')->whereIn('variant_id', $deletable)->delete();
        DB::table($variants)->whereIn('id', $deletable)->delete();
    }

    public function down(): void
    {
        // One-way: these rows were demo data with no source to rebuild from.
    }
};

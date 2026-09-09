<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete price rows whose priceable no longer exists.
 *
 * Found while verifying the SKU → variant move: 12 rows still pointed at
 * `product_sku` ids 1–12 after every real SKU had been repointed. Those ids do
 * not exist and did not exist before the migration either — the SKU table starts
 * at 13. They are leftovers from the delete-and-recreate save strategy
 * `SkuBuilderService` uses, which changes SKU ids on every product save and, at
 * some point, dropped rows without their prices.
 *
 * Harmless while nothing resolved them, but a price attached to nothing is a
 * trap: it is invisible in the admin, survives every backup, and will match the
 * next model that happens to reuse the id. The variant table already reuses ids
 * from 1 — the legacy demo variants sit exactly there — so this is the moment it
 * stops being theoretical.
 *
 * Deliberately narrow: only rows whose morph target is missing, checked per
 * type, so a row for a model this migration does not know about is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prices = config('lunar.database.table_prefix', 'lunar_').'prices';

        if (! Schema::hasTable($prices)) {
            return;
        }

        $targets = [
            'product_variant' => config('lunar.database.table_prefix', 'lunar_').'product_variants',
            'product_sku' => config('lunar.database.table_prefix', 'lunar_').'product_skus',
        ];

        foreach ($targets as $morph => $table) {
            if (! Schema::hasTable($table)) {
                // The model is gone entirely — every row for it is an orphan.
                DB::table($prices)->where('priceable_type', $morph)->delete();

                continue;
            }

            DB::table($prices)
                ->where('priceable_type', $morph)
                ->whereNotIn('priceable_id', fn ($query) => $query->select('id')->from($table))
                ->delete();
        }
    }

    public function down(): void
    {
        // Nothing to restore: these rows referenced records that no longer exist.
    }
};

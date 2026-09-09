<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the shop's `images` column on Lunar's variants to `image_asset_ids`.
 *
 * The old name shadowed `ProductVariant::images()`, Lunar's own BelongsToMany
 * to Media — and in Eloquent a real column always wins over a relation of the
 * same name. So `$variant->images` handed back this column's array of Asset ids
 * instead of loading the relation, and `getThumbnail()` died on
 * `->first()` against an array. The panel's product editor calls it: every
 * product with a variant carrying images 500'd.
 *
 * The two are genuinely different things that happened to share a word: this
 * column lists Media Library **Asset ids** the variant points at, while the
 * relation is media the variant **owns**. Renaming says which is which.
 *
 * Idempotent across both paths, which is the asymmetry this upgrade keeps being
 * bitten by: an upgraded database has `images` to rename, while a fresh install
 * never creates it (the SKU migration now writes the new name directly) and
 * this becomes a no-op.
 */
return new class extends Migration
{
    private function table(): string
    {
        return config('lunar.database.table_prefix').'product_variants';
    }

    public function up(): void
    {
        $table = $this->table();

        if (! Schema::hasColumn($table, 'images') || Schema::hasColumn($table, 'image_asset_ids')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->renameColumn('images', 'image_asset_ids');
        });
    }

    public function down(): void
    {
        $table = $this->table();

        if (! Schema::hasColumn($table, 'image_asset_ids') || Schema::hasColumn($table, 'images')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->renameColumn('image_asset_ids', 'images');
        });
    }
};

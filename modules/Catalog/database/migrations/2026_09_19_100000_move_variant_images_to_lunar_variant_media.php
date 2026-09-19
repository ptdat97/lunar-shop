<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Support\VariantImageColumn;

/**
 * Variant photos move to Lunar's own variant images, and the shop's column goes.
 *
 * `image_asset_ids` was the shop's per-variant photo list; Lunar 2.0 ships
 * the same idea as `media_product_variant` (ordered, with a primary), edited
 * by its own variant screen and read by `ProductVariant::getThumbnail()`. Two
 * stores for one thing meant the panel wrote one and the storefront read the
 * other. The pivot is now the only one (VariantImages); this moves every
 * non-empty set into it — see VariantImageColumn for how the old ids are read
 * — and then drops the column.
 *
 * Idempotent: a database without the column (a fresh install, or a second
 * run) has nothing to move.
 */
return new class extends Migration
{
    private function table(): string
    {
        return config('lunar.database.table_prefix').'product_variants';
    }

    public function up(): void
    {
        if (! Schema::hasColumn($this->table(), VariantImageColumn::COLUMN)) {
            return;
        }

        app(VariantImageColumn::class)->moveToLunar();

        Schema::table($this->table(), function (Blueprint $table): void {
            $table->dropColumn(VariantImageColumn::COLUMN);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn($this->table(), VariantImageColumn::COLUMN)) {
            return;
        }

        Schema::table($this->table(), function (Blueprint $table): void {
            $table->json(VariantImageColumn::COLUMN)->nullable();
        });

        app(VariantImageColumn::class)->restoreFromLunar();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `lunar_products.variables`, the shop's own axis definition.
 *
 * It described a product's variant axes as a free-form JSON blob back when this
 * shop owned its purchasable. Lunar 2.0 carries the same information properly:
 * shared `ProductOption`s with `ProductOptionValue`s that variants link to, and
 * the SKU → variant convergence moved every product onto them.
 *
 * The blob outlived its readers one at a time — the picker moved to VariantAxes,
 * swatch images to the option value's own `meta` — until only the search facets
 * were left, and they had become actively wrong: the *filter* already queried
 * the option tables while the *facet counts* still decoded the blob. Two sources
 * of truth, and the panel that now edits variants only ever writes one of them,
 * so the sidebar would have started offering values that filtered to nothing the
 * first time anyone edited a product in the admin.
 */
return new class extends Migration
{
    private function table(): string
    {
        return config('lunar.database.table_prefix').'products';
    }

    public function up(): void
    {
        if (! Schema::hasColumn($this->table(), 'variables')) {
            return;
        }

        Schema::table($this->table(), function (Blueprint $table): void {
            $table->dropColumn('variables');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn($this->table(), 'variables')) {
            return;
        }

        // The shape comes back; the content does not. Nothing reads it, so an
        // empty column is honest — it is what a rollback can actually offer.
        Schema::table($this->table(), function (Blueprint $table): void {
            $table->json('variables')->nullable();
        });
    }
};

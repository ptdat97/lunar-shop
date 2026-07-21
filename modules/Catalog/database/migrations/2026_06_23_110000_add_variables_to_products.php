<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

/**
 * The VaniCommerce-style flexible variant model stores the per-product variant
 * DEFINITIONS as a free-form JSON blob on the product itself — no shared option
 * catalogue, no FKs. Each entry is:
 *
 *   { "name": {"en": "Colour", "vi": "Màu"},
 *     "values": [ {"name": {"en": "Black"}, "image": "…"}, … ],
 *     "isImage": false }
 *
 * A ProductSku's `variants` array holds integer indexes into this blob (value
 * #j of variable #i), so a SKU is a positional Cartesian combination rather
 * than a relational join. This column is the single source of truth the admin
 * builder edits; SkuBuilderService reads it to (re)generate the SKU rows.
 *
 * Lives in the Catalog module but alters Lunar's `products` table directly —
 * Lunar has no extension point for product columns (§5). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'products', function (Blueprint $table) {
            if (! Schema::hasColumn($this->prefix.'products', 'variables')) {
                $table->json('variables')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'products', function (Blueprint $table) {
            if (Schema::hasColumn($this->prefix.'products', 'variables')) {
                $table->dropColumn('variables');
            }
        });
    }
};

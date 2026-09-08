<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Database\Migration;

/**
 * Per-variant columns for the marketplace-style variant builder (BeikeShop UI):
 *   - model:      display model/reference number.
 *   - cost_price: buying cost in minor units (profit tracking, admin-only —
 *                 never exposed to the storefront). Kept on the variant, not on
 *                 prices, because cost doesn't vary by currency/customer tier.
 *   - status:     'published' | 'disabled'. 'disabled' hides the variant from
 *                 the storefront AND is enforced as a real guard in CartService
 *                 (not just a UI flag — coding standards §17.4).
 *
 * Lunar 2.0 ships `model` and `cost_price` itself, so on a v2 baseline only
 * `status` is added here — the per-column guards already handle that.
 *
 * Lunar has no extension point for per-variant columns, so this alters its
 * table directly (§5), but lives in the Catalog module — no Lunar migration
 * file is touched. Idempotent.
 *
 * No `after()` anywhere: column order is cosmetic in MySQL, and naming a
 * neighbour couples this to the vendor's layout — which is exactly what broke
 * when 2.0 renamed `purchasable` to `selling_policy`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn($this->prefix.'product_variants', 'model')) {
                $table->string('model')->nullable();
            }
            if (! Schema::hasColumn($this->prefix.'product_variants', 'cost_price')) {
                $table->unsignedInteger('cost_price')->nullable();
            }
            if (! Schema::hasColumn($this->prefix.'product_variants', 'status')) {
                $table->string('status')->default('published')->index();
            }
        });

        // Backfill existing rows: the column default only applies to new rows,
        // so pre-existing variants would otherwise be NULL and slip past the
        // published-only storefront filter unpredictably.
        DB::table($this->prefix.'product_variants')
            ->whereNull('status')
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        Schema::table($this->prefix.'product_variants', function (Blueprint $table) {
            foreach (['model', 'cost_price', 'status'] as $column) {
                if (Schema::hasColumn($this->prefix.'product_variants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make "out of stock" actually mean out of stock.
 *
 * The selling policy defaults to `always` in Lunar's own migration — a mode that
 * means "sell it whether or not we hold any". Nothing in this codebase ever set
 * it, so every variant was a backorder variant, and the oversell guards in
 * DecrementStock and CartService (both of which correctly exempt
 * `backorder`/`always`) never fired.
 *
 * Measured before this change: stock 2, order 10 → checkout 200 OK, stock −8.
 *
 * A single-store fashion shop sells what it has. Admins can still switch an
 * individual variant to `backorder` or `always` in the product editor when they
 * genuinely want to sell ahead of delivery.
 */
return new class extends Migration
{
    /**
     * Lunar 2.0 renamed the column to `selling_policy` (upgrade spec 0007). An
     * upgraded database is renamed before this runs; a database built from the
     * v2 baseline never had the old name at all. Resolve it rather than
     * hardcoding either, so `migrate` works on both.
     */
    protected function column(): string
    {
        return Schema::hasColumn('lunar_product_variants', 'selling_policy')
            ? 'selling_policy'
            : 'purchasable';
    }

    public function up(): void
    {
        $column = $this->column();

        // Existing rows: every one of them is on Lunar's untouched default, so
        // none of them expresses a deliberate backorder decision.
        DB::table('lunar_product_variants')
            ->where($column, 'always')
            ->update([$column => 'in_stock']);

        // New rows default to the same. `change()` needs doctrine/dbal on some
        // stacks, so alter the column directly.
        DB::statement("ALTER TABLE lunar_product_variants MODIFY {$column} VARCHAR(255) NOT NULL DEFAULT 'in_stock'");
    }

    public function down(): void
    {
        $column = $this->column();

        DB::statement("ALTER TABLE lunar_product_variants MODIFY {$column} VARCHAR(255) NOT NULL DEFAULT 'always'");

        DB::table('lunar_product_variants')
            ->where($column, 'in_stock')
            ->update([$column => 'always']);
    }
};

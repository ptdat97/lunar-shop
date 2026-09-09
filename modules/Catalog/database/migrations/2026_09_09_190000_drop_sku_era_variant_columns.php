<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops two columns the SKU builder added to Lunar's variants.
 *
 *  - `status` ('published' | 'disabled') was this shop's notion of whether a
 *    purchasable is live. Lunar 2.0 has `enabled`, which is what the panel
 *    edits and what `isPurchasable()` reads — so the column was a second,
 *    competing answer to the same question sitting right beside the real one.
 *    Every row held 'published' while 54 variants were actually disabled, so it
 *    was not merely redundant but wrong.
 *  - `model` was a denormalised "Product / Colour / Size" label. That string is
 *    exactly what Lunar's shared ProductOption links now describe properly, and
 *    VariantAxes composes it on demand.
 *
 * `cost_price` is deliberately kept: it holds real per-variant data that cannot
 * be recomputed from anything else, and margin is a question a shop asks even
 * when no screen shows it today.
 */
return new class extends Migration
{
    private function table(): string
    {
        return config('lunar.database.table_prefix').'product_variants';
    }

    public function up(): void
    {
        $columns = array_values(array_filter(
            ['status', 'model'],
            fn (string $column) => Schema::hasColumn($this->table(), $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table($this->table(), function (Blueprint $table) use ($columns): void {
            // `status` carries an index; dropColumn removes it with the column
            // on MySQL, but naming it keeps the intent readable.
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table): void {
            if (! Schema::hasColumn($this->table(), 'status')) {
                $table->string('status')->default('published')->index();
            }

            if (! Schema::hasColumn($this->table(), 'model')) {
                $table->string('model')->nullable();
            }
        });
    }
};

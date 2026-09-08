<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the v2 baseline declares that `lunar:upgrade` never adds.
 *
 * `lunarphp/upgrade` 2.0.0-alpha.6 finishes by rewriting the `migrations` ledger
 * so the whole v2 baseline is marked as already run. That is what lets future
 * 2.x migrations layer cleanly — but it also means any column the 19 data
 * migrations forgot will never be created by anything, ever. Found by building
 * the v2 baseline into a scratch database and diffing `information_schema`
 * against the upgraded one; three columns were missing, and all three are read
 * by shipped v2 code:
 *
 *   lunar_customers.admin_notes        — panel CustomerEditController + its
 *                                        CustomerNotesRequest
 *   lunar_order_lines.refunded_quantity — RefundOrder increments it, and
 *                                        OrderLine::refundableQuantity() reads it
 *   lunar_product_options.type          — ProductOptionType (text/colour/swatch),
 *                                        the feature that replaces our old
 *                                        meta->display_type
 *
 * A fourth difference is a constraint, not a column: the baseline creates
 * `lunar_attributes.configuration` nullable while v1 left it NOT NULL, so an
 * attribute saved without configuration would fail on an upgraded database only.
 *
 * Every step is guarded, so this is a no-op on a database built from the v2
 * baseline (where the columns already exist) and safe to re-run.
 */
return new class extends Migration
{
    protected function prefix(): string
    {
        return config('lunar.database.table_prefix', 'lunar_');
    }

    public function up(): void
    {
        $customers = $this->prefix().'customers';
        $orderLines = $this->prefix().'order_lines';
        $productOptions = $this->prefix().'product_options';
        $attributes = $this->prefix().'attributes';

        if (Schema::hasTable($customers) && ! Schema::hasColumn($customers, 'admin_notes')) {
            Schema::table($customers, function (Blueprint $table) {
                $table->text('admin_notes')->nullable();
            });
        }

        if (Schema::hasTable($orderLines) && ! Schema::hasColumn($orderLines, 'refunded_quantity')) {
            Schema::table($orderLines, function (Blueprint $table) {
                $table->unsignedInteger('refunded_quantity')
                    ->default(0)
                    ->comment('Rollup of refund_lines.quantity for this line');
            });
        }

        if (Schema::hasTable($productOptions) && ! Schema::hasColumn($productOptions, 'type')) {
            Schema::table($productOptions, function (Blueprint $table) {
                $table->string('type')->default('text')->index();
            });
        }

        if (Schema::hasTable($attributes) && Schema::hasColumn($attributes, 'configuration')) {
            Schema::table($attributes, function (Blueprint $table) {
                $table->json('configuration')->nullable()->change();
            });
        }
    }

    /**
     * Only the columns are dropped. The `configuration` constraint is left
     * relaxed: tightening it back would fail on any row this migration's
     * lifetime allowed to be null.
     */
    public function down(): void
    {
        $customers = $this->prefix().'customers';
        $orderLines = $this->prefix().'order_lines';
        $productOptions = $this->prefix().'product_options';

        foreach ([[$customers, 'admin_notes'], [$orderLines, 'refunded_quantity'], [$productOptions, 'type']] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};

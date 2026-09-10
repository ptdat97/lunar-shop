<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When we last nudged the shopper about this cart.
 *
 * A column rather than a flag in `meta` because the sweep queries on it every
 * ten minutes: `whereNull` on an indexed column is the difference between an
 * index scan and reading every cart row to decode JSON.
 *
 * Nullable and no default, so every cart that already exists reads as
 * "never reminded" — which is true, and means the first sweep after deploy is
 * the only one that could surprise anyone. That is why the command ships with
 * a `--dry-run` and the scheduler entry is off until the shop turns it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_carts', function (Blueprint $table): void {
            $table->timestamp('abandoned_reminded_at')->nullable()->after('completed_at');

            // The sweep filters on "not reminded AND not completed AND stale".
            // Composite so the planner can serve the whole predicate from it.
            $table->index(['abandoned_reminded_at', 'completed_at'], 'carts_abandoned_sweep_index');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_carts', function (Blueprint $table): void {
            $table->dropIndex('carts_abandoned_sweep_index');
            $table->dropColumn('abandoned_reminded_at');
        });
    }
};

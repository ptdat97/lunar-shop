<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When we asked this customer for a review.
 *
 * Same shape and same reasoning as `lunar_carts.abandoned_reminded_at`: the
 * sweep filters on it, so it is an indexed column rather than a key in `meta`.
 *
 * Nullable with no default, so every existing order reads as "never asked" —
 * which is true, and is exactly why the feature ships off and the command has
 * a `--dry-run`. Switching it on without looking would email every customer the
 * shop has ever had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table): void {
            $table->timestamp('review_requested_at')->nullable()->after('placed_at');
            $table->index('review_requested_at', 'orders_review_request_index');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table): void {
            $table->dropIndex('orders_review_request_index');
            $table->dropColumn('review_requested_at');
        });
    }
};

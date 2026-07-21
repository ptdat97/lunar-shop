<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side "recently viewed", so a signed-in shopper sees the same list on
 * the web and in the app.
 *
 * The storefront keeps its localStorage list for guests (personalised, never
 * crawled) — this only mirrors it for people we can identify.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recently_viewed_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->timestamp('viewed_at');

            // Ordering key. A timestamp cannot do this job: two views a few
            // microseconds apart land in the same millisecond (measured: ~100%
            // of consecutive calls), so the list came back in an arbitrary order
            // and a just-viewed product could sort last. `sequence` only ever
            // increases, per user.
            $table->unsignedBigInteger('sequence');

            $table->timestamps();

            // One row per product per user: a re-view moves it to the top.
            $table->unique(['user_id', 'product_id']);
            // The list is read newest-first, per user.
            $table->index(['user_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed_products');
    }
};

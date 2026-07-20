<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back-in-stock ("notify me") subscriptions. A shopper leaves their email
 * against an out-of-stock SKU; when stock is replenished we email them and
 * mark the row notified. Keyed by (sku, email) so a shopper can't subscribe
 * twice to the same SKU.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lunar prefixes its tables (default "lunar_"), so reference the real
        // SKUs table for the FK rather than the unprefixed default.
        $skus = config('lunar.database.table_prefix', 'lunar_').'product_skus';

        Schema::create('stock_notifications', function (Blueprint $table) use ($skus): void {
            $table->id();
            $table->foreignId('product_sku_id')->constrained($skus)->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_sku_id', 'email']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};

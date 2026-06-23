<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back-in-stock ("notify me") subscriptions. A shopper leaves their email
 * against an out-of-stock variant; when stock is replenished we email them and
 * mark the row notified. Keyed by (variant, email) so a shopper can't subscribe
 * twice to the same variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lunar prefixes its tables (default "lunar_"), so reference the real
        // variants table for the FK rather than the unprefixed default.
        $variants = config('lunar.database.table_prefix', 'lunar_').'product_variants';

        Schema::create('stock_notifications', function (Blueprint $table) use ($variants): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained($variants)->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'email']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};

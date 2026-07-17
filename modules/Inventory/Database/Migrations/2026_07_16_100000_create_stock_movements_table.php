<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock ledger: an append-only record of every change to a SKU's stock —
 * sales (reservation), releases (cancel/refund), manual adjustments, restocks
 * and inline edits. Each row carries the signed delta plus the before/after
 * levels, an optional reason and the causer (staff, or null for system/CLI),
 * so the "why did stock change" question is always answerable.
 *
 * Stock lives on the flexible SKU (product_skus.quantity), so the ledger keys
 * off product_sku_id.
 *
 * Append-only: rows are never updated, so there is no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lunar prefixes its tables (default "lunar_") — reference the real one.
        $skus = config('lunar.database.table_prefix', 'lunar_').'product_skus';

        Schema::create('stock_movements', function (Blueprint $table) use ($skus): void {
            $table->id();
            $table->foreignId('product_sku_id')->constrained($skus)->cascadeOnDelete();
            $table->string('type', 20);          // sale|release|adjustment|restock|manual|edit
            $table->integer('quantity');         // signed delta (+/-)
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('reason')->nullable();
            $table->nullableMorphs('causer');    // Staff, or null for system/CLI
            $table->unsignedBigInteger('order_id')->nullable(); // not constrained: keep history if the order is deleted
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('type');
            $table->index(['product_sku_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

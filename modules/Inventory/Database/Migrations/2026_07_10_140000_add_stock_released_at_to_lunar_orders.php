<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an order whose reserved stock has been put back.
 *
 * `DecrementStock` reserves inventory the moment an order is created — which,
 * for a gateway payment, happens *before* the customer has paid. Nothing ever
 * released it: an abandoned VNPay checkout, a failed payment, a cancelled order
 * or a refund all left the units gone for good.
 *
 * The timestamp makes the release idempotent: restocking twice would invent
 * inventory out of nothing, which is worse than the leak it fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table) {
            $table->timestamp('stock_released_at')->nullable()->after('placed_at');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table) {
            $table->dropColumn('stock_released_at');
        });
    }
};

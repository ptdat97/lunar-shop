<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved body-measurement profile per customer (Size Intelligence v2). Prefills
 * the "find my size" form so a returning shopper doesn't re-enter measurements.
 * One row per customer; columns mirror SizeChartRow::MEASUREMENTS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('lunar_customers')->cascadeOnDelete();
            $table->decimal('bust', 6, 2)->nullable();
            $table->decimal('waist', 6, 2)->nullable();
            $table->decimal('hip', 6, 2)->nullable();
            $table->decimal('shoulder', 6, 2)->nullable();
            $table->decimal('length', 6, 2)->nullable();
            $table->decimal('inseam', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_measurements');
    }
};

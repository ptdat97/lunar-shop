<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained(
                (new \Lunar\Models\ProductVariant())->getTable()
            )->cascadeOnDelete();
            $table->string('size')->nullable();
            $table->string('length')->nullable();
            $table->string('fit')->nullable();
            $table->string('shoulder')->nullable();
            $table->string('waist')->nullable();
            $table->string('bust')->nullable();
            $table->string('hip')->nullable();
            $table->string('inseam')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_dimensions');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Models\Product;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained(
                (new Product)->getTable()
            )->cascadeOnDelete();
            $table->string('material')->nullable();
            $table->string('composition')->nullable();
            $table->text('care_instruction')->nullable();
            $table->string('fabric_weight')->nullable();
            $table->string('stretch')->nullable();
            $table->string('transparency')->nullable();
            $table->string('lining')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_materials');
    }
};

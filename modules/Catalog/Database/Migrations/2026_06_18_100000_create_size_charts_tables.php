<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable size charts (defined once, picked by many products).
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable(); // tops, bottoms, dresses…
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Rows (sizes) within a chart.
        Schema::create('size_chart_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_chart_id')->constrained('size_charts')->cascadeOnDelete();
            $table->string('size');
            $table->string('fit')->nullable();
            $table->string('bust')->nullable();
            $table->string('waist')->nullable();
            $table->string('hip')->nullable();
            $table->string('shoulder')->nullable();
            $table->string('length')->nullable();
            $table->string('inseam')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // One size chart per product (link table, keeps Lunar schema untouched).
        Schema::create('product_size_chart', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->foreignId('size_chart_id')->constrained('size_charts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_size_chart');
        Schema::dropIfExists('size_chart_rows');
        Schema::dropIfExists('size_charts');
    }
};

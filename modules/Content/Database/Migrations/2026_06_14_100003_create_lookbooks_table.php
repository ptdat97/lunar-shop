<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookbooks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        $prefix = config('lunar.database.table_prefix', '');
        $productsTable = $prefix.'products';

        Schema::create('lookbook_items', function (Blueprint $table) use ($productsTable) {
            $table->id();
            $table->foreignId('lookbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained($productsTable)->cascadeOnDelete();
            $table->string('caption')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookbook_items');
        Schema::dropIfExists('lookbooks');
    }
};

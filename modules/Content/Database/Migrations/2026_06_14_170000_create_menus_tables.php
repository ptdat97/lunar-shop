<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();   // header / footer / mobile
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('type')->default('link'); // link | dropdown | mega | mega-column | banner
            $table->string('label')->nullable();
            $table->string('url')->nullable();
            $table->unsignedBigInteger('collection_id')->nullable(); // → /collections/{slug}
            $table->string('target')->default('_self');
            $table->string('image')->nullable();   // banner/promo
            $table->string('badge')->nullable();   // "New" / "Sale"
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort']);
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};

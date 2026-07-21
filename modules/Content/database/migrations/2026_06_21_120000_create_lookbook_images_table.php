<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookbook_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lookbook_id')->constrained()->cascadeOnDelete();
            // Holds a Lunar Asset id (same convention as lookbooks.cover_image),
            // resolved to a URL via MediaLibraryService::url().
            $table->string('image');
            $table->string('caption')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookbook_images');
    }
};

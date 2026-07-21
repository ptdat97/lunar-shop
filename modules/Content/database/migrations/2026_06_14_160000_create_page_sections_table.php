<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            // Logical page handle (e.g. "home"). No CMS Pages table yet — just a key.
            $table->string('page_handle')->default('home')->index();
            $table->string('type');               // maps to a Blade partial in theme/sections
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();  // per-section content/config
            $table->timestamps();

            $table->index(['page_handle', 'enabled', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};

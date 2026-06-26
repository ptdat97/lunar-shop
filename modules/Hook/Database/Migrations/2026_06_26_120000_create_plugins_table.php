<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Install state for plugins (the internal Plugin SDK). The allow-list in
 * config/plugins.php decides what LOADS; this table only records what's been
 * INSTALLED — so lifecycle commands are idempotent (don't re-run migrations,
 * know the version that's on disk vs. installed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_id')->unique();   // e.g. "acme/loyalty"
            $table->string('version')->nullable();    // version recorded at install
            $table->boolean('active')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plugin config store (the admin Plugins tab writes here). A JSON bag keyed
 * by the plugin so a plugin's settings live with its install record — the SDK
 * owns the storage, plugins just read/write their slice via PluginSettings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};

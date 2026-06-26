<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shoppable lookbook hotspots: pin a lookbook item to a point on a photo. All
 * columns are nullable — items without a position just don't render a pin, so
 * existing lookbooks keep working unchanged ("shop the set" still applies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lookbook_items', function (Blueprint $table) {
            // Percentage coordinates (0–100) of the pin on its image.
            $table->float('pos_x')->nullable()->after('caption');
            $table->float('pos_y')->nullable()->after('pos_x');
            // Which lookbook image the pin sits on (null = cover image).
            $table->foreignId('image_id')->nullable()->after('pos_y')
                ->constrained('lookbook_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lookbook_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_id');
            $table->dropColumn(['pos_x', 'pos_y']);
        });
    }
};

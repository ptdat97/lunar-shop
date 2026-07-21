<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vietnam administrative units (2-tier model, 2025 reform): provinces/cities
 * and their wards/communes — no district level. Used by the checkout + address
 * book province/ward dropdowns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vn_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // matinhBNV (e.g. "01")
            $table->string('name');                  // tentinhmoi
            $table->timestamps();
        });

        Schema::create('vn_wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('vn_provinces')->cascadeOnDelete();
            $table->string('code', 16)->unique();    // maphuongxa
            $table->string('name');                  // tenphuongxa
            $table->timestamps();

            $table->index(['province_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vn_wards');
        Schema::dropIfExists('vn_provinces');
    }
};

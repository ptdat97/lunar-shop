<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping zones: a named region (matched by country, optionally narrowed to a
 * set of states/provinces) with a flat rate and an optional free-shipping
 * threshold. The FlatRateShippingModifier picks the most specific matching zone
 * for the cart's shipping address; a country-wide zone with no states acts as
 * the fallback.
 *
 * This DB-backed model supersedes the static config/shipping.php rate (which
 * remains the default when no zone matches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // 2-letter ISO country code this zone applies to (e.g. "VN").
            $table->string('country_code', 2)->index();
            // Optional list of state/province names this zone is limited to. Empty
            // = the whole country.
            $table->json('states')->nullable();
            // Flat shipping rate in minor units.
            $table->unsignedInteger('rate')->default(0);
            // Free shipping when cart sub-total >= this (minor units). 0 disables.
            $table->unsignedInteger('free_threshold')->default(0);
            $table->boolean('enabled')->default(true)->index();
            // Lower runs first when several zones match (most specific wins, then
            // priority).
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};

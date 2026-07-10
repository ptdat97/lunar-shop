<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Headless cart identity.
 *
 * Lunar's CartSessionManager keys the current cart off the HTTP session, so a
 * Bearer-token client (mobile app, POS) has no way to say "this is my cart" —
 * every request minted a new one. Signed-in clients can be resolved through
 * `user_id`, but a guest on the app needs an opaque handle it can store and
 * send back as `X-Cart-Token`.
 *
 * Nullable: web carts never get one, so no behaviour changes for the storefront.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_carts', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_carts', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};

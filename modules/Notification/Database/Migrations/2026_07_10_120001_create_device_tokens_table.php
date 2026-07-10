<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push targets for a user's devices.
 *
 * A device token is issued by the platform (FCM/APNs), can be reassigned to a
 * different user when someone else signs in on the same handset, and is revoked
 * when the vendor tells us it is stale — hence unique on the token, not on the
 * (user, token) pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 16);              // ios | android | web
            $table->string('device_name')->nullable();   // "Pixel 8"
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};

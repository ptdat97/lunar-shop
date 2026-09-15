<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One invite code per customer.
 *
 * The code is a separate table rather than a column on `lunar_customers`
 * because that table belongs to Lunar: a column added here would travel with
 * the vendor package (and be dropped by any reset of it), while this table is
 * the shop's own and can grow.
 *
 * `fingerprint` is the device the customer was first seen on when they opened
 * their own referral page. A claim arriving from that same device is refused —
 * the cheapest defence against inviting yourself through a second account,
 * which `user_id` alone cannot see (a second account is a different user).
 */
return new class extends Migration
{
    public function up(): void
    {
        $customers = config('lunar.database.table_prefix', 'lunar_').'customers';

        Schema::create('referral_codes', function (Blueprint $table) use ($customers): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained($customers)->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('fingerprint', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};

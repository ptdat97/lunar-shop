<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per successful referral: who invited whom, and where the reward got to.
 *
 * `referred_customer_id` is UNIQUE: a customer can be referred once. Without
 * that, someone could be handed a welcome coupon per friend, and the second
 * claim would overwrite the first one's qualifying order — two rewards for one
 * purchase.
 *
 * The lifecycle is stored, not derived (there is no ledger behind a referral):
 *
 *   claimed   the friend registered through the code; welcome coupon issued
 *             and `welcome_discount_id` set
 *   awaiting  the friend's first order was PAID; `order_id` + `qualified_at`
 *             set, waiting out the return window
 *   rewarded  the return window passed and the order was not given back;
 *             `reward_discount_id` + `rewarded_at` set
 *   voided    the order came back (or staff voided it): `voided_reason`
 *
 * `qualified_at` also carries the index: the release sweep asks "what has been
 * waiting longer than N days", which is exactly (status, qualified_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::create('referral_claims', function (Blueprint $table) use ($prefix): void {
            $table->id();

            $table->foreignId('referral_code_id')->constrained('referral_codes')->cascadeOnDelete();
            $table->foreignId('referrer_customer_id')->constrained($prefix.'customers')->cascadeOnDelete();
            $table->foreignId('referred_customer_id')->nullable()->unique()->constrained($prefix.'customers')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 16)->default('claimed');
            $table->foreignId('order_id')->nullable()->constrained($prefix.'orders')->nullOnDelete();

            // Coupon ids, not FKs: `lunar_discounts` rows are the shop's coupons
            // and a discount can be deleted from the panel while the referral
            // history stays readable.
            $table->unsignedBigInteger('welcome_discount_id')->nullable();
            $table->unsignedBigInteger('reward_discount_id')->nullable();

            $table->string('fingerprint', 64)->nullable();
            $table->string('voided_reason', 32)->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'qualified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_claims');
    }
};

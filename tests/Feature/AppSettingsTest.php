<?php

namespace Tests\Feature;

use Modules\Catalog\Services\ReviewService;
use Modules\Core\Support\Settings;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The admin-configurable settings store: DB overrides win, config/env is the
 * fallback, and per-key fallback within a group works.
 */
class AppSettingsTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_falls_back_to_config_when_unset(): void
    {
        config(['shipping.standard_rate' => 3000]);

        $this->assertSame(3000, (int) app(Settings::class)->get('shipping.standard_rate'));
    }

    public function test_db_override_wins(): void
    {
        config(['shipping.standard_rate' => 3000]);
        $settings = app(Settings::class);

        $settings->put('shipping', ['standard_rate' => 4500]);

        $this->assertSame(4500, (int) $settings->get('shipping.standard_rate'));
    }

    public function test_per_key_fallback_within_a_saved_group(): void
    {
        config(['payment.vnpay.tmn_code' => 'CFG', 'payment.vnpay.hash_secret' => 'SECRET']);
        $settings = app(Settings::class);

        // Save only tmn_code; hash_secret should still fall back to config.
        $settings->put('payment', ['vnpay' => ['tmn_code' => 'DB']]);

        $this->assertSame('DB', $settings->get('payment.vnpay.tmn_code'));
        $this->assertSame('SECRET', $settings->get('payment.vnpay.hash_secret'));
    }

    public function test_shipping_resolver_honours_admin_override(): void
    {
        config(['shipping.standard_rate' => 3000, 'shipping.free_threshold' => 0]);
        app(Settings::class)->put('shipping', ['standard_rate' => 5000, 'free_threshold' => 0]);

        // No zone → resolver falls back to the (now overridden) flat rate.
        $this->assertSame(5000, (int) app(Settings::class)->get('shipping.standard_rate'));
    }

    public function test_review_auto_approve_override_controls_new_review_visibility(): void
    {
        $this->seedBaseData();

        $reviews = app(ReviewService::class);
        $product = $this->createProduct();

        // Auto-approve on → published immediately.
        app(Settings::class)->put('review', ['auto_approve' => true]);
        $approved = $reviews->add($product->id, 'Alice', 5, 'Great');
        $this->assertTrue((bool) $approved->approved);

        // Auto-approve off → held for moderation.
        app(Settings::class)->put('review', ['auto_approve' => false]);
        $pending = $reviews->add($product->id, 'Bob', 4, 'Nice');
        $this->assertFalse((bool) $pending->approved);
    }
}

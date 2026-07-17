<?php

namespace Tests\Feature;

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Theme\Services\ThemeSettings;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Transactional emails carry the store's branding: the logo from Theme settings
 * (falling back to the header logo, then to the site name) and the accent colour
 * on buttons. Emails render outside the site origin, so the logo URL must be
 * absolute.
 */
class EmailBrandingTest extends TestCase
{
    use CreatesStorefrontData;

    /** Write a theme settings group and drop the singleton's cache/memo. */
    private function setBrand(array $brand): ThemeSettings
    {
        $settings = app(ThemeSettings::class);
        $settings->set('brand', $brand);

        return $settings;
    }

    /** A placed order with one line + a shipping address — all a mail needs. */
    private function makeOrder(): Order
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'BRAND-0001',
            'sub_total' => 5000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 5000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'type' => 'physical',
            'description' => 'Test Tee',
            'quantity' => 1,
            'unit_price' => 5000,
            'unit_quantity' => 1,
            'sub_total' => 5000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 5000,
        ]);

        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => 'Mai',
            'last_name' => 'Nguyen',
            'contact_email' => 'mai@example.com',
            'line_one' => '1 Test St',
            'city' => 'Hanoi',
        ]);

        return $order->fresh(['lines', 'shippingAddress']);
    }

    public function test_email_logo_is_rendered_as_absolute_url(): void
    {
        $this->seedBaseData();
        config(['app.url' => 'https://shop.test']);
        $this->setBrand(['email_logo' => 'theme/logo/brand.png', 'email_accent' => '#18181b']);

        $html = (new OrderConfirmationMail($this->makeOrder()))->render();

        // The media disk serves a root-relative "/media" URL; an email client
        // could never resolve that, so it must be absolutised.
        $this->assertStringContainsString('src="https://shop.test/media/theme/logo/brand.png"', $html);
    }

    public function test_email_logo_falls_back_to_header_logo(): void
    {
        $this->seedBaseData();
        config(['app.url' => 'https://shop.test']);
        $settings = app(ThemeSettings::class);
        $settings->set('brand', ['email_logo' => '', 'email_accent' => '#18181b']);
        $settings->set('general', ['logo' => 'theme/logo/header.png', 'logo_footer' => '', 'favicon' => '']);

        $html = (new OrderConfirmationMail($this->makeOrder()))->render();

        $this->assertStringContainsString('https://shop.test/media/theme/logo/header.png', $html);
    }

    public function test_without_any_logo_the_email_shows_the_site_name(): void
    {
        $this->seedBaseData();
        $settings = app(ThemeSettings::class);
        $settings->set('brand', ['email_logo' => '', 'email_accent' => '#18181b']);
        $settings->set('general', ['logo' => '', 'logo_footer' => '', 'favicon' => '']);

        $html = (new OrderConfirmationMail($this->makeOrder()))->render();

        $this->assertStringNotContainsString('class="logo"', $html);
        $this->assertStringContainsString(config('app.name'), $html);
    }

    public function test_accent_colour_is_inlined_onto_the_button(): void
    {
        $this->seedBaseData();
        $this->setBrand(['email_logo' => '', 'email_accent' => '#ff0066']);

        $html = (new OrderConfirmationMail($this->makeOrder()))->render();

        // CssToInlineStyles inlines the theme rule onto the button element.
        $this->assertStringContainsString('#ff0066', $html);
    }

    public function test_invalid_accent_falls_back_and_cannot_inject_css(): void
    {
        $this->seedBaseData();
        $settings = $this->setBrand([
            'email_logo' => '',
            'email_accent' => 'red; } body { display:none; ',
        ]);

        $this->assertSame('#18181b', $settings->emailAccent());

        $html = (new OrderConfirmationMail($this->makeOrder()))->render();
        $this->assertStringNotContainsString('display:none', $html);
    }
}

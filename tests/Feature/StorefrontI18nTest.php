<?php

namespace Tests\Feature;

use Modules\Theme\Support\StorefrontI18n;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The theme's JavaScript must speak the visitor's language.
 *
 * ~26 strings were hardcoded English in the enhancers, and they surfaced at the
 * moments a shopper is closest to paying: the free-shipping nudge in the cart
 * ("Add 250.000 ₫ more for free shipping."), the coupon result at checkout, the
 * membership progress line. The translations mostly existed already — the JS had
 * no way to reach them, because the storefront had no JS i18n mechanism at all.
 *
 * Two failure modes this pins down, both silent:
 *
 * 1. A key in the payload that has no translation renders the KEY on screen
 *    (`cart.increase`), and nothing errors.
 * 2. A `:placeholder` that the JS substitutes must survive translation — a
 *    Vietnamese string that drops `:amount` shows a nudge with no number in it.
 */
class StorefrontI18nTest extends TestCase
{
    use CreatesStorefrontData;

    /** Every key must resolve — a missing one shows the key to a customer. */
    public function test_every_key_resolves_in_both_languages(): void
    {
        foreach (['vi', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (StorefrontI18n::payload() as $jsKey => $translated) {
                $this->assertStringNotContainsString(
                    'storefront.',
                    $translated,
                    "[{$locale}] khoá [{$jsKey}] chưa có bản dịch — khách sẽ thấy chính chuỗi khoá.",
                );

                $this->assertNotSame('', trim($translated), "[{$locale}] khoá [{$jsKey}] rỗng.");
            }
        }
    }

    /**
     * Placeholders are substituted by the JS helper, not by Laravel — the value
     * is only known client-side. Losing one in translation costs the number.
     */
    public function test_placeholders_survive_translation(): void
    {
        $expected = [
            'cart.free_shipping_remaining' => [':amount'],
            'membership.discount_every_order' => [':percent'],
            'membership.spend_to_reach' => [':amount', ':tier'],
            'size.also_consider' => [':sizes'],
        ];

        foreach (['vi', 'en'] as $locale) {
            app()->setLocale($locale);
            $payload = StorefrontI18n::payload();

            foreach ($expected as $key => $placeholders) {
                foreach ($placeholders as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $payload[$key] ?? '',
                        "[{$locale}] [{$key}] mất placeholder {$placeholder} — chuỗi sẽ hiện thiếu số.",
                    );
                }
            }
        }
    }

    /** The payload has to actually reach the page, or none of this matters. */
    public function test_the_payload_is_embedded_in_the_page(): void
    {
        $this->seedBaseData();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-storefront-i18n', $html);

        preg_match('/<script type="application\/json" data-storefront-i18n>(.*?)<\/script>/s', $html, $m);

        $this->assertNotEmpty($m[1] ?? '', 'Khối i18n rỗng.');

        $decoded = json_decode($m[1], true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('cart.free_shipping_remaining', $decoded);

        // Vietnamese must arrive as real characters, not \uXXXX escapes — the
        // block is read by JSON.parse either way, but escaped Vietnamese is
        // several times the bytes on every single page.
        $this->assertStringNotContainsString('\u1ed', $m[1]);
    }

    /**
     * `application/json` is data, not code: the browser does not execute it, so
     * an enforcing `script-src 'self'` does not block it. Worth pinning, because
     * switching CSP to enforce is a planned change and this would break silently
     * in the customer's browser with no server-side trace.
     */
    public function test_the_payload_survives_an_enforcing_csp(): void
    {
        $this->seedBaseData();
        config(['security.csp.mode' => 'enforce']);

        $response = $this->get('/')->assertOk();

        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('data-storefront-i18n', $response->getContent());
    }
}

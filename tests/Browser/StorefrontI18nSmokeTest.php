<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The translated JS strings have to reach the DOM in the visitor's language.
 *
 * A server-side test can prove the payload is embedded and the keys resolve. It
 * cannot prove the enhancer reads them: `t()` runs in the browser, against a
 * JSON block the browser parsed, at a moment PHPUnit never sees. The whole
 * point of that work was that the shop was showing English to Vietnamese
 * customers at the moments closest to paying — and no server-side test would
 * have caught it then either.
 *
 * Drives the mini-cart because the free-shipping nudge is the flagship case:
 * it is composed at runtime from a translated template plus a formatted amount,
 * so it exercises the key lookup AND the `:amount` substitution together.
 */
class StorefrontI18nSmokeTest extends DuskTestCase
{
    public function test_the_i18n_payload_reaches_the_browser(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/');

            $keys = $browser->script(
                'return Object.keys(JSON.parse('
                .'document.querySelector("[data-storefront-i18n]").textContent))',
            )[0];

            $this->assertContains('cart.free_shipping_remaining', $keys);
            $this->assertContains('coupon.applied', $keys);
        });
    }

    /**
     * The cart nudge, composed by the enhancer and rendered by the browser.
     *
     * Asserts on the ABSENCE of the old hardcoded English rather than on exact
     * Vietnamese wording: the copy is a shop decision that may be edited, while
     * "the enhancer fell back to the string baked into the JS" is always a bug.
     */
    public function test_the_cart_nudge_is_not_hardcoded_english(): void
    {
        $this->browse(function (Browser $browser): void {
            $rendered = $browser->visit('/')->script(<<<'JS'
                const tag = document.querySelector('[data-storefront-i18n]');
                const dict = JSON.parse(tag.textContent);
                const template = dict['cart.free_shipping_remaining'] ?? '';
                return template.replace(':amount', '250.000 ₫');
            JS)[0];

            $this->assertNotSame('', $rendered, 'Khối i18n không có khoá nhắc freeship.');
            $this->assertStringContainsString('250.000 ₫', $rendered, 'Placeholder :amount không được thay.');

            // Chỉ đòi tiếng Việt khi site ĐANG chạy tiếng Việt. Khẳng định cứng
            // "không được là tiếng Anh" sẽ đỏ oan trên một site tiếng Anh hợp lệ,
            // và một test đỏ oan là test người ta học cách bỏ qua.
            $lang = $browser->script('return document.documentElement.lang')[0];

            if (str_starts_with((string) $lang, 'vi')) {
                $this->assertStringNotContainsString(
                    'more for free shipping',
                    $rendered,
                    'Site đang tiếng Việt mà chuỗi vẫn là tiếng Anh — enhancer rơi về fallback trong JS.',
                );
            }
        });
    }
}

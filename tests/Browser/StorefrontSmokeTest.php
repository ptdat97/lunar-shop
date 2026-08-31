<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Read-only storefront E2E smoke across the public modules: Catalog (home,
 * collection, search, product), Content (page, lookbooks), Promotion, Checkout
 * (cart), Customer (login, register, wishlist).
 *
 * Runs against the live APP_URL + dev DB like any Dusk test, so it asserts the
 * two things PHPUnit never sees: that a page rendered real content (not a 404)
 * and that the browser console stayed clean — the Livewire/Alpine entangle
 * errors that only ever show up in a real browser.
 *
 * Deliberately read-only: it never adds to cart or writes data, so it is safe
 * to run repeatedly on the dev DB without cleanup.
 */
class StorefrontSmokeTest extends DuskTestCase
{
    /**
     * Console entries that are noise rather than failure.
     *
     * Mirrors VariantMediaPickerTest::IGNORED. Add entries here only after
     * proving they are benign (a real diagnostic trimming rule, not a way to
     * hide failures).
     */
    private const IGNORED = [
        'was preloaded using link preload but not used',
        'Download the React DevTools',
        'favicon',
    ];

    /** The 404/missing page title the framework renders (APP_LOCALE=vi). */
    private const NOT_FOUND_TITLE = 'Không tìm thấy';

    /**
     * @return array<int, string>
     */
    private function consoleErrors(Browser $browser): array
    {
        $messages = [];

        foreach ($browser->driver->manage()->getLog('browser') as $entry) {
            $message = (string) ($entry['message'] ?? '');

            if ($message === '') {
                continue;
            }

            foreach (self::IGNORED as $ignore) {
                if (str_contains($message, $ignore)) {
                    continue 2;
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }

    private function assertConsoleClean(Browser $browser, string $page): void
    {
        $errors = $this->consoleErrors($browser);

        $this->assertSame(
            [],
            $errors,
            "Browser console was not clean on {$page}:\n  - ".implode("\n  - ", $errors),
        );
    }

    /**
     * Visit a storefront page, wait for the shared header, and assert it is the
     * real page (not a 404) and that the console stayed clean.
     *
     * @param  string  $url  absolute or root-relative URL
     * @param  string  $expectedContent  text the real page must contain
     * @param  string|null  $expectedTitlePart  optional title substring (default: any)
     */
    private function assertPageHealthy(Browser $browser, string $url, string $expectedContent, ?string $expectedTitlePart = null): void
    {
        $browser->visit($url)
            // Header logo is on every page; waiting for it means the SSR HTML
            // (and its enhancers) finished rendering, not just the TCP response.
            ->waitFor('.navbar-brand', 15)
            // Let the on-load enhancers (cart drawer, countdown, search) settle.
            ->pause(600);

        $title = (string) $browser->driver->getTitle();

        $this->assertStringNotContainsString(
            self::NOT_FOUND_TITLE,
            $title,
            "[{$url}] resolved to the 404 page.",
        );

        if ($expectedTitlePart !== null) {
            $this->assertStringContainsString(
                $expectedTitlePart,
                $title,
                "[{$url}] rendered an unexpected title.",
            );
        }

        $browser->assertSee($expectedContent);

        $this->assertConsoleClean($browser, $url);
    }

    public function test_home_and_catalog_pages_are_healthy(): void
    {
        $this->browse(function (Browser $browser) {
            $this->assertPageHealthy($browser, '/', '14-Day Returns', null);
            $this->assertPageHealthy($browser, '/collections/men', 'Men');
            $this->assertPageHealthy($browser, '/search?q=ao', 'Kết quả');
            $this->assertPageHealthy(
                $browser,
                '/products/ribbed-knit-cardigan-27',
                'Áo cardigan dệt kim gân',
            );
        });
    }

    public function test_content_and_promotion_pages_are_healthy(): void
    {
        $this->browse(function (Browser $browser) {
            $this->assertPageHealthy($browser, '/pages/about-us', 'About Us');
            $this->assertPageHealthy($browser, '/lookbooks', 'Bộ sưu tập');
            $this->assertPageHealthy($browser, '/lookbooks/spring-essentials', 'Spring Essentials');
            $this->assertPageHealthy($browser, '/promotions', 'Khuyến mãi');
            $this->assertPageHealthy($browser, '/promotions/buy-2-get-10', 'Khuyến mãi');
        });
    }

    public function test_cart_and_customer_pages_are_healthy(): void
    {
        $this->browse(function (Browser $browser) {
            $this->assertPageHealthy($browser, '/cart', 'Giỏ hàng');
            $this->assertPageHealthy($browser, '/wishlist', 'Yêu thích');
            $this->assertPageHealthy($browser, '/login', 'Đăng nhập');
            $this->assertPageHealthy($browser, '/register', 'Tạo tài khoản');
        });
    }
}

<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Mobile bottom navigation and the facet bottom sheet (roadmap §15).
 *
 * The bottom nav did not just get added — it took over the header's action
 * icons. Before this, a phone-width header carried six tap targets (hamburger,
 * language, search, wishlist, account, cart) and the menu drawer repeated
 * account and wishlist a second time. The duplication tests below are the point
 * of this file: adding a nav is easy, and quietly ending up with two of
 * everything is the failure mode.
 */
class MobileNavigationTest extends TestCase
{
    use CreatesStorefrontData;

    /** Count how many times a link to a named route appears in the markup. */
    private function linkCount(string $html, string $route): int
    {
        return substr_count($html, 'href="'.route($route).'"');
    }

    public function test_the_bottom_nav_renders_on_storefront_pages(): void
    {
        $this->seedBaseData();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-bottom-nav', $html);

        foreach (['storefront.home', 'storefront.search', 'storefront.wishlist', 'storefront.cart', 'storefront.account'] as $route) {
            $this->assertGreaterThan(0, $this->linkCount($html, $route), "Bottom nav is missing {$route}.");
        }
    }

    /**
     * The whole point of §15: on a phone each destination is reachable once.
     *
     * SSR renders both sets — desktop needs its header icons — so the guarantee
     * is not "one link in the DOM" but a clean breakpoint pairing: the header
     * group is desktop-only, the bottom nav is mobile-only. Exactly one is ever
     * on screen.
     */
    public function test_the_header_actions_and_bottom_nav_never_show_together(): void
    {
        $this->seedBaseData();

        $html = $this->get('/')->assertOk()->getContent();

        // Header action group: hidden below lg.
        $this->assertMatchesRegularExpression(
            '/<div class="d-none d-lg-flex align-items-center gap-3">/',
            $html,
            'The header action group must be desktop-only, or it doubles the bottom nav.',
        );

        // Bottom nav: hidden from lg up.
        $this->assertMatchesRegularExpression(
            '/<nav class="bottom-nav d-lg-none"/',
            $html,
            'The bottom nav must be mobile-only, or it doubles the header.',
        );
    }

    /**
     * The menu drawer used to repeat account and wishlist in a pinned footer.
     * Those are removed outright — the drawer is the category tree now.
     */
    public function test_the_menu_drawer_no_longer_repeats_account_and_wishlist(): void
    {
        $this->seedBaseData();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('mobile-menu__action', $html);
    }

    /** The header keeps the two things the bottom nav does not carry. */
    public function test_the_header_keeps_the_menu_toggle_and_the_logo(): void
    {
        $this->seedBaseData();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-bs-target="#mobileMenu"', $html);
        $this->assertStringContainsString('navbar-brand', $html);
    }

    /**
     * Checkout runs on its own layout and must stay free of it: nothing should
     * tempt a shopper away mid-payment.
     */
    public function test_checkout_has_no_bottom_nav(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->get('/checkout')
            ->assertOk()
            ->assertDontSee('data-bottom-nav', false);
    }

    /** Both search entry points open the same panel, so both carry the hook. */
    public function test_the_bottom_nav_search_opens_the_search_panel(): void
    {
        $this->seedBaseData();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'data-search-toggle'),
            'The bottom nav search should carry the same toggle hook as the header.',
        );
    }

    /** Facets become a bottom sheet on phones and stay a sidebar on desktop. */
    public function test_the_listing_filters_render_as_a_responsive_offcanvas(): void
    {
        $this->seedBaseData();
        $this->createProduct(['stock' => 5]);

        $html = $this->get('/search')->assertOk()->getContent();

        // offcanvas-lg = sheet below lg, plain static block from lg up.
        $this->assertStringContainsString('offcanvas-lg', $html);
        $this->assertStringContainsString('id="shopFilters"', $html);
        $this->assertStringContainsString('data-bs-target="#shopFilters"', $html);
        $this->assertStringContainsString('data-active-facet-count', $html);
    }

    /** The GET form still works with the sheet markup wrapped around it. */
    public function test_filtering_still_works_without_javascript(): void
    {
        $this->seedBaseData();
        $this->createProduct(['stock' => 5]);

        $this->get('/search')
            ->assertOk()
            ->assertSee('data-facet-form', false);
    }
}

<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Tests\DuskTestCase;

/**
 * The free-shipping progress strip (roadmap §13) rendered by the enhancers,
 * not just the payload that feeds it.
 *
 * CartResource::freeShippingInfo() shape is pinned by the Feature test — this
 * proves the OTHER half that PHPUnit never sees: the enhancer reads the payload
 * and draws a real `<div class="progress-bar">` into the drawer and the cart
 * page (DOM after JS ran, e2e-testing.md §1).
 *
 * Runs against the live APP_URL + dev DB like any Dusk test. Deliberately
 * creates a cart, so it must clean up after itself (§3.4): remove the line it
 * added no matter whether the assertions pass.
 */
class FreeShippingProgressTest extends DuskTestCase
{
    public function test_progress_bar_renders_in_drawer_and_cart_page(): void
    {
        if ((int) config('shipping.free_threshold') <= 0) {
            $this->markTestSkipped('Free-shipping threshold is disabled in this environment.');
        }

        $product = Product::query()
            ->with('defaultUrl')
            ->get()
            ->first(fn (Product $p) => $p->defaultUrl !== null
                && $p->variants->first()?->getTotalInventory() > 0);

        $this->assertNotNull($product, 'No product with the first variant in stock to add to cart.');

        $slug = $product->defaultUrl->slug;

        $this->browse(function (Browser $browser) use ($slug) {
            $browser->visit('/products/'.$slug)
                ->waitFor('[data-add-to-cart-btn]:not([disabled])', 15)
                // Adding to cart opens the mini-cart, whose strip should show.
                ->press('[data-add-to-cart-btn]')
                ->waitFor('#shoppingCart [data-cart-shipping] .progress-bar', 15)
                ->assertVisible('#shoppingCart [data-cart-shipping] .progress-bar');

            // Line id for the cleanup below — read from the rendered drawer.
            $lineId = $browser->attribute('#shoppingCart [data-cart-body] [data-line]', 'data-line');

            try {
                // The cart page uses the same helper via cart-page.js.
                $browser->visit('/cart')
                    // `waitUntil` evaluates real JS in the page, so `:not/[hidden]`
                    // nuances and WebDriver's CSS quirks are irrelevant: the strip
                    // exists exactly when the enhancer drew it.
                    ->waitUntil(
                        'document.querySelector(".freeship-strip .progress-bar") !== null',
                        15,
                    )
                    // Note: `.page-cart` is on <body> itself, so we can't use it
                    // with Dusk (it prepends `body `, making `body .page-cart`
                    // look for a descendant). `.freeship-strip` is unique enough.
                    ->assertVisible('.freeship-strip .progress-bar');
            } finally {
                // Remove the line we added (dots in the selector are class
                // selectors, so quote the data attribute value).
                $browser->script(
                    'fetch(`/api/v1/cart/lines/'.$lineId.'`, { method: "DELETE" });',
                );
            }
        });
    }
}
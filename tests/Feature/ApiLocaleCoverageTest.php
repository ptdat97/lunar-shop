<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Services\TokenAwareCartSession;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Locale resolution reaches every `/api/v1` route, not just the ones in the
 * framework `api` middleware group.
 *
 * `SetApiLocale` used to be pushed onto the `api` group only, which covered 17
 * of 52 routes. Cart, checkout, orders and account are registered under
 * `web`/`storefront` (Lunar's cart needs a session), so a headless client asking
 * for `?locale=vi` got the store default back from all of them.
 */
class ApiLocaleCoverageTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_every_api_route_resolves_a_locale_except_the_health_probe(): void
    {
        $router = app('router');

        $uncovered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1'))
            ->reject(fn ($r) => collect($router->gatherRouteMiddleware($r))
                ->contains(fn ($m) => is_string($m) && str_contains($m, 'SetApiLocale')))
            ->map(fn ($r) => $r->uri())
            ->unique()
            ->values();

        // The health probe deliberately runs with no middleware at all, so it
        // stays reachable when the cache (which locale settings read) is down.
        $this->assertSame(['api/v1/health'], $uncovered->all());
    }

    public function test_cart_payload_honours_the_locale_query(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct([
            'name' => 'Wool Coat',
            'name_vi' => 'Áo khoác len',
        ]);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        // `/api/v1/cart` lives in the `storefront` group — before this it always
        // answered in the store default.
        $this->getJson('/api/v1/cart?locale=vi')
            ->assertSuccessful()
            ->assertJsonPath('data.lines.0.name', 'Áo khoác len');

        $this->getJson('/api/v1/cart?locale=en')
            ->assertSuccessful()
            ->assertJsonPath('data.lines.0.name', 'Wool Coat');
    }

    public function test_a_headless_client_can_pick_the_locale_per_request(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['name' => 'Wool Coat', 'name_vi' => 'Áo khoác len']);

        $token = $this->withHeaders(['X-Client' => 'app'])
            ->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1])
            ->assertSuccessful()
            ->json('data.cart_token');

        $this->withHeaders([TokenAwareCartSession::HEADER => $token])
            ->getJson('/api/v1/cart?locale=vi')
            ->assertJsonPath('data.lines.0.name', 'Áo khoác len');
    }

    public function test_accept_language_is_honoured_when_no_query_is_given(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['name' => 'Wool Coat', 'name_vi' => 'Áo khoác len']);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->withHeaders(['Accept-Language' => 'vi'])
            ->getJson('/api/v1/cart')
            ->assertJsonPath('data.lines.0.name', 'Áo khoác len');
    }

    public function test_a_storefront_language_choice_outranks_the_query_string(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['name' => 'Wool Coat', 'name_vi' => 'Áo khoác len']);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        // The visitor switched the storefront to Vietnamese; a stray `?locale=en`
        // on an XHR must not silently flip the language they picked in the UI.
        $this->withSession(['locale' => 'vi'])
            ->getJson('/api/v1/cart?locale=en')
            ->assertJsonPath('data.lines.0.name', 'Áo khoác len');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Every `api/v1/*` route is rate limited, whichever middleware group it was
 * registered in.
 *
 * `throttleApi()` only covers the framework `api` group; cart, checkout, orders
 * and account routes sit in `web`/`storefront` because they need a session. That
 * previously left most of the API — order placement included — with no limiter.
 */
class ApiRateLimitTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_route_outside_the_api_group_is_now_throttled(): void
    {
        $this->seedBaseData();

        // `GET /api/v1/cart` is registered in the `storefront` group, so
        // throttleApi() never covered it. It must be limited now.
        $cart = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/cart' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($cart, 'expected GET api/v1/cart to exist');
        $this->assertNotContains(
            'throttle:api',
            $cart->gatherMiddleware(),
            'route should NOT declare its own throttle — coverage comes from the global middleware',
        );

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/cart')->assertSuccessful();
        }

        $this->getJson('/api/v1/cart')->assertStatus(429);
    }

    public function test_read_endpoint_is_throttled_after_the_api_limit(): void
    {
        $this->seedBaseData();

        // `api` limiter = 120/min. The 121st request must be rejected.
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/products')->assertOk();
        }

        $this->getJson('/api/v1/products')->assertStatus(429);
    }

    public function test_rate_limit_headers_are_present(): void
    {
        $this->seedBaseData();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 120);
    }

    public function test_the_health_probe_is_exempt(): void
    {
        $this->seedBaseData();

        // The limiter is cache-backed. Throttling the probe would make it fail
        // during the very cache outage it exists to report.
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeaderMissing('X-RateLimit-Limit');
    }

    public function test_checkout_uses_a_stricter_limiter_than_reads(): void
    {
        $this->seedBaseData();

        // `checkout` limiter = 10/min, far below the 120/min read budget. The
        // 11th POST is rejected before it ever reaches checkout validation.
        for ($i = 0; $i < 10; $i++) {
            $res = $this->postJson('/api/v1/checkout', []);
            $this->assertNotSame(429, $res->status(), "request {$i} throttled too early");
        }

        $this->postJson('/api/v1/checkout', [])->assertStatus(429);
    }

    public function test_throttled_response_uses_the_api_error_envelope(): void
    {
        $this->seedBaseData();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/checkout', []);
        }

        $this->postJson('/api/v1/checkout', [])
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_storefront_pages_are_not_rate_limited(): void
    {
        $this->seedBaseData();

        // The middleware is global but guards on the `api/v1/*` URI, so HTML
        // pages must be untouched — no limiter, no headers.
        $response = $this->get('/');

        $response->assertHeaderMissing('X-RateLimit-Limit');
    }
}

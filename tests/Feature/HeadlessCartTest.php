<?php

namespace Tests\Feature;

use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Modules\Checkout\Services\TokenAwareCartSession;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Cart + checkout for stateless clients (mobile app, POS).
 *
 * Lunar keys the current cart off the HTTP session, and `AuthManager::user()`
 * resolves through the session-backed `web` guard — so a Bearer-token request
 * matched neither the session key nor the "user's active cart" fallback, and
 * minted a brand-new cart on every call. Writes additionally 419'd, because
 * cart/checkout inherit CSRF from the `web` group.
 *
 * `TokenAwareCartSession` (bound over Lunar's `CartSessionInterface`) resolves
 * the cart from `X-Cart-Token`, then from the token user's active cart. The web
 * storefront keeps taking Lunar's original session path, unchanged.
 */
class HeadlessCartTest extends TestCase
{
    use CreatesStorefrontData;

    /** Headers a guest app sends on its very first call (no handle yet). */
    private const APP = ['X-Client' => 'app'];

    private function variantId(): int
    {
        return $this->createProduct()->variants->first()->id;
    }

    private function addToCart(array $headers, int $variantId, int $qty = 1)
    {
        return $this->withHeaders($headers)
            ->postJson('/api/v1/cart', ['variant_id' => $variantId, 'quantity' => $qty]);
    }

    public function test_guest_app_gets_a_cart_token_on_its_first_call(): void
    {
        $this->seedBaseData();

        $response = $this->addToCart(self::APP, $this->variantId())->assertSuccessful();

        $token = $response->json('data.cart_token');

        $this->assertNotEmpty($token, 'a headless client must receive a cart handle');
        $this->assertSame($token, Cart::find($response->json('data.id'))->public_token);
    }

    public function test_guest_app_keeps_the_same_cart_across_requests(): void
    {
        $this->seedBaseData();
        $variant = $this->variantId();

        $first = $this->addToCart(self::APP, $variant)->assertSuccessful();
        $token = $first->json('data.cart_token');

        // A second, cookie-less request reclaims the very same basket.
        $second = $this->withHeaders([TokenAwareCartSession::HEADER => $token])
            ->getJson('/api/v1/cart')
            ->assertSuccessful();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $second->json('data.lines_count'));
    }

    public function test_writes_accumulate_on_the_same_headless_cart(): void
    {
        $this->seedBaseData();
        $variant = $this->variantId();

        $token = $this->addToCart(self::APP, $variant, 1)->json('data.cart_token');

        $this->addToCart([TokenAwareCartSession::HEADER => $token], $variant, 2)
            ->assertSuccessful();

        $this->withHeaders([TokenAwareCartSession::HEADER => $token])
            ->getJson('/api/v1/cart')
            ->assertJsonPath('data.lines_count', 3);
    }

    public function test_bearer_token_client_reuses_its_users_cart(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $token = $user->createToken('app')->plainTextToken;
        $variant = $this->variantId();

        $auth = ['Authorization' => "Bearer {$token}"];

        $first = $this->addToCart($auth, $variant)->assertSuccessful();
        $second = $this->withHeaders($auth)->getJson('/api/v1/cart')->assertSuccessful();

        // Previously each call minted a new cart for the same user.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($user->id, Cart::find($first->json('data.id'))->user_id);
    }

    public function test_a_users_cart_cannot_be_claimed_by_its_handle_alone(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        // Build the owned cart directly. Placing it via an authenticated request
        // first would leave Sanctum's guard and Lunar's cart-session singleton
        // memoized for the rest of the test process, so the "anonymous" request
        // below would still be treated as that user.
        $owned = Cart::create([
            'currency_id' => Currency::getDefault()->id,
            'channel_id' => Channel::getDefault()->id,
            'user_id' => $user->id,
            'public_token' => 'owned-cart-handle',
        ]);

        $stolen = $this->withHeaders([TokenAwareCartSession::HEADER => 'owned-cart-handle'])
            ->getJson('/api/v1/cart')
            ->assertSuccessful();

        // An anonymous client presenting the handle gets a fresh cart, not the
        // owned one — the handle is only as secret as the device holding it.
        $this->assertNotSame($owned->id, $stolen->json('data.id'));
        $this->assertSame(0, $stolen->json('data.lines_count'));
    }

    public function test_the_storefront_payload_never_leaks_the_cart_handle(): void
    {
        $this->seedBaseData();

        // Cookie session, no headless headers → contract unchanged.
        $this->getJson('/api/v1/cart')
            ->assertSuccessful()
            ->assertJsonMissingPath('data.cart_token');
    }

    public function test_storefront_still_uses_the_session_cart(): void
    {
        $this->seedBaseData();
        $variant = $this->variantId();

        $first = $this->postJson('/api/v1/cart', ['variant_id' => $variant, 'quantity' => 1])
            ->assertSuccessful();
        $second = $this->getJson('/api/v1/cart')->assertSuccessful();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertNull(Cart::find($first->json('data.id'))->public_token);
    }

    public function test_guest_app_can_place_an_order_end_to_end(): void
    {
        $this->seedBaseData();
        $variant = $this->variantId();

        $token = $this->addToCart(self::APP, $variant)->json('data.cart_token');
        $headers = [TokenAwareCartSession::HEADER => $token];

        $this->withHeaders($headers)
            ->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])
            ->assertSuccessful();

        $this->withHeaders($headers)
            ->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])
            ->assertSuccessful();

        // No cookie, no session, no CSRF token — this used to be impossible.
        $this->withHeaders($headers)
            ->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'payment-offline')
            ->assertJsonStructure(['data' => ['id', 'reference', 'status', 'lines']]);
    }
}

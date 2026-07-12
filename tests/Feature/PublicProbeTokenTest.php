<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The three PUBLIC login-state probes must recognise a bearer token.
 *
 * `GET /customer`, `GET /wishlist` and `GET /customer/measurements` are public
 * by design — the storefront calls them on every page load and a guest must get
 * `200 + empty`, never a 401. To stay public they sit in the `web` middleware
 * group, and that is where the bug lived: the default guard in that group is
 * session-backed, so `$request->user()` could not see a bearer token.
 *
 * The result was the worst kind of failure — a *silent* one. A token client with
 * a perfectly valid token got `200 {"data": null}`, which is indistinguishable
 * from "you are a guest". The Next.js storefront read that as "not signed in"
 * and bounced every freshly-logged-in shopper straight back to the login page,
 * in an infinite loop, with no error anywhere to explain why.
 *
 * The fix is `$request->user('sanctum')` — the sanctum guard resolves BOTH a
 * cookie session and a bearer token, and still yields null for a real guest.
 *
 * Mutation-check: change any of these back to `$request->user()` and the
 * matching test goes red.
 */
class PublicProbeTokenTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_customer_probe_recognises_a_bearer_token(): void
    {
        $this->seedBaseData();
        $user = $this->createUser(['email' => 'token@test.local']);
        $token = $user->createToken('app', ['customer:*'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer')
            ->assertOk();

        // The whole bug: this used to be null for a token client.
        $this->assertNotNull($response->json('data'), 'A valid bearer token must not look like a guest.');
        $this->assertSame('token@test.local', $response->json('data.email'));
    }

    public function test_wishlist_probe_recognises_a_bearer_token(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $user = $this->createUser();
        $token = $user->createToken('app', ['customer:*'])->plainTextToken;

        // Put something on the list through the authenticated endpoint.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertSuccessful();

        // Reading it back with the SAME token must show it — not an empty list.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_measurements_probe_recognises_a_bearer_token(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $token = $user->createToken('app', ['customer:*'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/measurements', ['chest' => 96, 'waist' => 78])
            ->assertSuccessful();

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/measurements')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data, 'A token client must see the profile it just saved.');
    }

    /**
     * The other half of the contract: these routes stay PUBLIC. A guest gets a
     * guest payload and a 200 — never a 401. Swapping in `auth:sanctum` to fix
     * the bug above would have broken exactly this.
     */
    public function test_probes_still_return_a_guest_payload_without_a_token(): void
    {
        $this->seedBaseData();

        $this->getJson('/api/v1/customer')->assertOk()->assertJson(['data' => null]);
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJson(['data' => []]);
        $this->getJson('/api/v1/customer/measurements')->assertOk()->assertJson(['data' => []]);
    }
}

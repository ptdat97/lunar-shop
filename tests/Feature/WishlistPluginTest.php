<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Phase 4 / B.1 — Wishlist extracted from the Customer module into the
 * acme/wishlist plugin (enabled by default). Behaviour is unchanged: same
 * routes/names/middleware and the same wishlist_items table. These tests lock
 * that the plugin-served endpoints work end-to-end (no wishlist test existed
 * before the split).
 */
class WishlistPluginTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_guest_wishlist_index_is_empty_and_does_not_401(): void
    {
        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('product_ids', []);
    }

    public function test_toggle_requires_auth(): void
    {
        $this->postJson('/api/v1/wishlist', ['product_id' => 1])->assertUnauthorized();
    }

    public function test_authenticated_user_can_toggle_and_list_wishlist(): void
    {
        $user = User::create([
            'name' => 'Mai',
            'email' => 'mai@example.test',
            'password' => bcrypt('password123'),
        ]);
        $product = $this->createProduct(['slug' => 'wished-tee']);

        $this->actingAs($user);

        // Add.
        $this->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('data.in_wishlist', true)
            ->assertJsonPath('data.count', 1);

        // Listed.
        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonPath('product_ids', [$product->id]);

        // Toggle off.
        $this->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('data.in_wishlist', false)
            ->assertJsonPath('data.count', 0);
    }

    public function test_wishlist_page_renders(): void
    {
        $this->get('/wishlist')->assertOk();
    }
}

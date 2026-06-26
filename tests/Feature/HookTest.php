<?php

namespace Tests\Feature;

use Modules\Platform\Facades\Hook;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Support\Hooks;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Hook module: the shared action/filter seam, plus the live cross-module wiring
 * (Inventory vetoing an oversell add-to-cart through `product.purchasable`).
 */
class HookTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_manager_is_a_shared_singleton(): void
    {
        $this->assertSame(app(HookManager::class), app(HookManager::class));
    }

    public function test_filters_run_in_priority_order_and_transform_the_value(): void
    {
        Hook::addFilter('price', fn (int $v) => $v + 1, priority: 20);
        Hook::addFilter('price', fn (int $v) => $v * 2, priority: 10);

        // priority 10 (×2) runs before priority 20 (+1): (5 × 2) + 1 = 11.
        $this->assertSame(11, Hook::applyFilters('price', 5));
    }

    public function test_filters_receive_extra_args(): void
    {
        Hook::addFilter('greeting', fn (string $v, string $name) => "{$v} {$name}");

        $this->assertSame('Hi Mai', Hook::applyFilters('greeting', 'Hi', ['Mai']));
    }

    public function test_actions_run_every_listener_for_side_effects(): void
    {
        $calls = [];
        Hook::addAction('ping', function (string $who) use (&$calls) { $calls[] = $who; });
        Hook::addAction('ping', function (string $who) use (&$calls) { $calls[] = strtoupper($who); });

        Hook::doAction('ping', ['a']);

        $this->assertSame(['a', 'A'], $calls);
    }

    public function test_applying_an_unregistered_filter_returns_the_value_unchanged(): void
    {
        $this->assertFalse(Hook::has('nope'));
        $this->assertSame('x', Hook::applyFilters('nope', 'x'));
    }

    public function test_inventory_vetoes_oversell_add_to_cart_via_the_purchasable_hook(): void
    {
        // The Inventory filter is registered for real at boot.
        $this->assertTrue(Hook::has(Hooks::PRODUCT_PURCHASABLE));

        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();
        $variant->update(['purchasable' => 'in_stock']);

        // Within stock: allowed.
        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertSuccessful();

        // Beyond stock: vetoed with a validation error, no line added.
        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 99])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('quantity');
    }

    public function test_product_resource_payload_is_filtered_and_enriched_by_inventory(): void
    {
        $this->assertTrue(Hook::has(Hooks::PRODUCT_RESOURCE));

        $product = $this->createProduct(['slug' => 'hooked-tee', 'stock' => 7]);
        $product->variants->first()->update(['purchasable' => 'in_stock']);

        // The product API payload carries Inventory's `availability` block,
        // contributed purely through the hook (ProductResource has no Inventory
        // dependency).
        $this->getJson('/api/v1/products/hooked-tee')
            ->assertOk()
            ->assertJsonPath('data.availability.in_stock', true)
            ->assertJsonPath('data.availability.total_quantity', 7);
    }

    public function test_a_module_can_add_an_arbitrary_field_to_the_product_payload(): void
    {
        Hook::addFilter(Hooks::PRODUCT_RESOURCE, function (array $data, $product): array {
            $data['badge'] = 'New';

            return $data;
        });

        $this->createProduct(['slug' => 'badged-tee']);

        $this->getJson('/api/v1/products/badged-tee')
            ->assertOk()
            ->assertJsonPath('data.badge', 'New');
    }

    public function test_order_placed_action_fires_on_checkout(): void
    {
        $captured = null;
        Hook::addAction(Hooks::ORDER_PLACED, function ($order) use (&$captured) {
            $captured = $order->reference;
        });

        $product = $this->createProduct(['price' => 5000, 'stock' => 10]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        $res = $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $this->assertNotNull($captured);
        $this->assertSame($res->json('data.reference'), $captured);
    }

    public function test_order_status_changed_action_fires_with_previous_status(): void
    {
        $transitions = [];
        Hook::addAction(Hooks::ORDER_STATUS_CHANGED, function ($order, string $previous) use (&$transitions) {
            $transitions[] = [$previous, $order->status];
        });

        $order = \Lunar\Models\Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'status' => 'awaiting-payment',
        ]);

        $order->update(['status' => 'dispatched']);

        $this->assertContains(['awaiting-payment', 'dispatched'], $transitions);
    }

    public function test_order_shipped_action_fires_on_the_dispatch_transition(): void
    {
        $shipped = null;
        Hook::addAction(Hooks::ORDER_SHIPPED, function ($order, string $previous) use (&$shipped) {
            $shipped = [$order->id, $previous];
        });

        $order = \Lunar\Models\Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'status' => 'payment-received',
        ]);

        $order->update(['status' => 'dispatched']);

        $this->assertSame([$order->id, 'payment-received'], $shipped);

        // A non-dispatch transition must NOT fire order.shipped.
        $shipped = null;
        $order->update(['status' => 'awaiting-payment']);
        $this->assertNull($shipped);
    }

    public function test_customer_registered_action_fires_on_register(): void
    {
        $registered = null;
        Hook::addAction(Hooks::CUSTOMER_REGISTERED, function ($user) use (&$registered) {
            $registered = $user->email;
        });

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Mai',
            'email' => 'mai@example.test',
            'password' => 'password123',
        ])->assertCreated();

        $this->assertSame('mai@example.test', $registered);
    }

    public function test_customer_logged_in_action_fires_on_login(): void
    {
        \App\Models\User::create([
            'name' => 'Lan',
            'email' => 'lan@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $loggedIn = null;
        Hook::addAction(Hooks::CUSTOMER_LOGGED_IN, function ($user) use (&$loggedIn) {
            $loggedIn = $user->email;
        });

        $this->postJson('/api/v1/auth/login', [
            'email' => 'lan@example.test',
            'password' => 'password123',
        ])->assertOk();

        $this->assertSame('lan@example.test', $loggedIn);
    }

    public function test_cart_line_actions_fire_on_add_update_remove(): void
    {
        $events = [];
        Hook::addAction(Hooks::CART_LINE_ADDED, function ($cart, $variant, int $qty) use (&$events) {
            $events[] = ['added', $variant->id, $qty];
        });
        Hook::addAction(Hooks::CART_LINE_UPDATED, function ($cart, int $lineId, int $qty) use (&$events) {
            $events[] = ['updated', $qty];
        });
        Hook::addAction(Hooks::CART_LINE_REMOVED, function ($cart, int $lineId) use (&$events) {
            $events[] = ['removed'];
        });

        $product = $this->createProduct(['price' => 5000, 'stock' => 10]);
        $variantId = $product->variants->first()->id;

        $this->postJson('/api/v1/cart', ['variant_id' => $variantId, 'quantity' => 2])->assertSuccessful();
        $lineId = $this->getJson('/api/v1/cart')->json('data.lines.0.id');
        $this->patchJson("/api/v1/cart/lines/{$lineId}", ['quantity' => 3])->assertSuccessful();
        $this->deleteJson("/api/v1/cart/lines/{$lineId}")->assertSuccessful();

        $this->assertSame(['added', $variantId, 2], $events[0]);
        $this->assertContains(['updated', 3], $events);
        $this->assertContains(['removed'], $events);
    }

    public function test_checkout_address_and_shipping_actions_fire(): void
    {
        $fired = [];
        Hook::addAction(Hooks::CHECKOUT_ADDRESS_SET, function ($cart) use (&$fired) {
            $fired[] = 'address';
        });
        Hook::addAction(Hooks::CHECKOUT_SHIPPING_SELECTED, function ($cart, string $id) use (&$fired) {
            $fired[] = "shipping:{$id}";
        });

        $product = $this->createProduct(['price' => 5000, 'stock' => 10]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        $this->assertContains('address', $fired);
        $this->assertContains('shipping:standard', $fired);
    }

    public function test_payment_methods_filter_is_the_source_of_allowed_methods(): void
    {
        // A plugin adds a gateway purely through the filter — no core edit.
        Hook::addFilter(Hooks::CHECKOUT_PAYMENT_METHODS, function (array $methods): array {
            $methods[] = 'momo';

            return $methods;
        });

        $this->assertContains('momo', app(\Modules\Checkout\Services\CheckoutService::class)->paymentMethods());

        // And checkout validation now accepts it (reaches placeOrder, where the
        // unknown driver fails) instead of rejecting it as an invalid value.
        $product = $this->createProduct(['price' => 5000, 'stock' => 10]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'momo'])
            ->assertJsonMissingValidationErrors('payment_type');
    }

    public function test_product_viewed_action_fires_on_the_detail_page(): void
    {
        $viewed = [];
        Hook::addAction(Hooks::PRODUCT_VIEWED, function ($product) use (&$viewed) {
            $viewed[] = $product->id;
        });

        $product = $this->createProduct(['slug' => 'viewed-tee']);

        // Storefront SSR view + headless API view both count.
        $this->get('/products/viewed-tee')->assertOk();
        $this->getJson('/api/v1/products/viewed-tee')->assertOk();

        $this->assertSame([$product->id, $product->id], $viewed);
    }

    public function test_product_created_and_updated_actions_fire(): void
    {
        $events = [];
        Hook::addAction(Hooks::PRODUCT_CREATED, function ($product) use (&$events) {
            $events[] = 'created';
        });
        Hook::addAction(Hooks::PRODUCT_UPDATED, function ($product) use (&$events) {
            $events[] = 'updated';
        });

        $product = $this->createProduct();
        $product->update(['status' => 'draft']);

        $this->assertContains('created', $events);
        $this->assertContains('updated', $events);
    }

    public function test_related_filter_can_swap_the_related_products(): void
    {
        $replacement = $this->createProduct(['slug' => 'plugin-pick']);

        Hook::addFilter(Hooks::PRODUCT_RELATED, function ($related, $product, int $limit) use ($replacement) {
            return collect([$replacement]);
        });

        $product = $this->createProduct(['slug' => 'main-tee']);
        $related = app(\Modules\Product\Services\ProductService::class)->related($product);

        $this->assertCount(1, $related);
        $this->assertSame($replacement->id, $related->first()->id);
    }

    public function test_inventory_events_fire_on_stock_transitions(): void
    {
        $events = [];
        Hook::addAction(Hooks::INVENTORY_OUT_OF_STOCK, function ($variant) use (&$events) {
            $events[] = 'out';
        });
        Hook::addAction(Hooks::INVENTORY_LOW_STOCK, function ($variant, int $stock) use (&$events) {
            $events[] = "low:{$stock}";
        });
        Hook::addAction(Hooks::INVENTORY_RESTOCKED, function ($variant, int $stock) use (&$events) {
            $events[] = "restock:{$stock}";
        });

        $product = $this->createProduct(['stock' => 20]);
        $variant = $product->variants->first();

        $variant->update(['stock' => 3]);   // 20 → 3: crosses below threshold (5)
        $variant->update(['stock' => 0]);   // 3 → 0: out of stock
        $variant->update(['stock' => 12]);  // 0 → 12: restocked

        $this->assertContains('low:3', $events);
        $this->assertContains('out', $events);
        $this->assertContains('restock:12', $events);
    }

    public function test_search_performed_action_fires_for_a_keyword_search(): void
    {
        $searches = [];
        Hook::addAction(Hooks::SEARCH_PERFORMED, function (string $term, int $total) use (&$searches) {
            $searches[] = [$term, $total];
        });

        $this->createProduct(['name' => 'Linen Shirt', 'slug' => 'linen-shirt']);

        $this->getJson('/api/v1/search?q=linen')->assertOk();

        // A blank browse (no term) must NOT fire it.
        $this->getJson('/api/v1/search?q=')->assertOk();

        $this->assertCount(1, $searches);
        $this->assertSame('linen', $searches[0][0]);
    }
}

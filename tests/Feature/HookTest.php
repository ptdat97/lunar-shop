<?php

namespace Tests\Feature;

use Modules\Hook\Facades\Hook;
use Modules\Hook\Services\HookManager;
use Modules\Hook\Support\Hooks;
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
}

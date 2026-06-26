<?php

namespace Tests\Feature;

use Modules\Hook\Facades\Hook;
use Modules\Hook\Support\Hooks;
use Modules\Hook\Support\PayloadContract;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * E4 — payload contract regression. The hookable payloads (product/cart/order)
 * are stable contracts: hooks may ADD keys, never remove the core ones. These
 * tests pin PayloadContract::REQUIRED_KEYS against the live API responses, so a
 * stray filter or refactor that drops a core key fails CI — and even prove a
 * misbehaving plugin filter can't silently delete one.
 */
class PayloadContractTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_product_payload_keeps_its_required_keys(): void
    {
        $this->createProduct(['slug' => 'contract-tee']);

        $data = $this->getJson('/api/v1/products/contract-tee')->assertOk()->json('data');

        foreach (PayloadContract::requiredKeys(Hooks::PRODUCT_RESOURCE) as $key) {
            $this->assertArrayHasKey($key, $data, "Product payload lost required key [{$key}].");
        }
    }

    public function test_cart_payload_keeps_its_required_keys(): void
    {
        $product = $this->createProduct(['price' => 5000, 'stock' => 5]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);

        $data = $this->getJson('/api/v1/cart')->assertSuccessful()->json('data');

        foreach (PayloadContract::requiredKeys(Hooks::CART_RESOURCE) as $key) {
            $this->assertArrayHasKey($key, $data, "Cart payload lost required key [{$key}].");
        }
    }

    public function test_a_plugin_filter_that_drops_a_core_key_is_detectable(): void
    {
        // Simulate a buggy plugin that removes a required key from the payload.
        Hook::addFilter(Hooks::PRODUCT_RESOURCE, function (array $data): array {
            unset($data['name']);

            return $data;
        });

        $this->createProduct(['slug' => 'broken-tee']);
        $data = $this->getJson('/api/v1/products/broken-tee')->assertOk()->json('data');

        // The contract check catches it (this is what CI would flag).
        $missing = array_diff(PayloadContract::requiredKeys(Hooks::PRODUCT_RESOURCE), array_keys($data));
        $this->assertSame(['name'], array_values($missing));
    }
}

<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/customer/addresses')->assertUnauthorized();
    }

    public function test_crud_lifecycle(): void
    {
        $user = $this->createUser();

        // Empty to start.
        $this->actingAs($user)->getJson('/api/v1/customer/addresses')
            ->assertOk()->assertJsonCount(0, 'data');

        // Create.
        $created = $this->actingAs($user)
            ->postJson('/api/v1/customer/addresses', $this->shippingPayload(['shipping_default' => true]))
            ->assertCreated()
            ->assertJsonPath('data.shipping_default', true);
        $id = $created->json('data.id');

        $this->actingAs($user)->getJson('/api/v1/customer/addresses')->assertJsonCount(1, 'data');

        // Update.
        $this->actingAs($user)
            ->patchJson("/api/v1/customer/addresses/{$id}", $this->shippingPayload(['city' => 'Hanoi']))
            ->assertOk()->assertJsonPath('data.city', 'Hanoi');

        // Delete.
        $this->actingAs($user)->deleteJson("/api/v1/customer/addresses/{$id}")->assertOk();
        $this->actingAs($user)->getJson('/api/v1/customer/addresses')->assertJsonCount(0, 'data');
    }

    public function test_validation(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/api/v1/customer/addresses', ['first_name' => 'Only'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_name', 'line_one', 'state', 'city', 'country_id']);
    }

    public function test_only_one_shipping_default(): void
    {
        $user = $this->createUser();

        $first = $this->actingAs($user)
            ->postJson('/api/v1/customer/addresses', $this->shippingPayload(['shipping_default' => true]))
            ->json('data.id');

        $this->actingAs($user)
            ->postJson('/api/v1/customer/addresses', $this->shippingPayload(['shipping_default' => true]));

        // The first address should no longer be the default.
        $this->assertDatabaseHas('lunar_addresses', ['id' => $first, 'shipping_default' => false]);
    }

    public function test_cannot_touch_another_users_address(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();

        $id = $this->actingAs($owner)
            ->postJson('/api/v1/customer/addresses', $this->shippingPayload())
            ->json('data.id');

        $this->actingAs($other)->patchJson("/api/v1/customer/addresses/{$id}", $this->shippingPayload())
            ->assertNotFound();
        $this->actingAs($other)->deleteJson("/api/v1/customer/addresses/{$id}")
            ->assertNotFound();
    }
}

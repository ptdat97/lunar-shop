<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Address;
use Modules\Customer\Services\AddressService;
use Modules\Customer\Services\CustomerResolver;
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

    /**
     * create() writes the address and then clears the default flag on its
     * siblings — two statements upholding one invariant. If the second fails,
     * the transaction must take the first with it, otherwise the customer ends
     * up with two defaults and checkout picks one arbitrarily.
     */
    public function test_a_failed_default_sync_rolls_back_the_new_address(): void
    {
        $user = $this->createUser();
        $service = app(AddressService::class);
        $customer = app(CustomerResolver::class)->forUser($user);

        $existing = $service->create($customer, $this->shippingPayload(['shipping_default' => true]));

        $before = Address::count();

        // Fail the sibling UPDATE only. Hooked at the query layer because
        // syncDefaults() uses a query-builder update, which fires no model
        // events. Not DDL either: MySQL commits implicitly on ALTER, which would
        // break RefreshDatabase's outer transaction and void the assertion.
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'update `lunar_addresses`')) {
                throw new \RuntimeException('sibling update failed');
            }
        });

        try {
            $service->create($customer, $this->shippingPayload(['shipping_default' => true]));
            $this->fail('expected the sibling update to fail');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, Address::count(), 'the half-written address must be rolled back');
        $this->assertDatabaseHas('lunar_addresses', ['id' => $existing->id, 'shipping_default' => true]);
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

<?php

namespace Tests\Feature;

use Modules\Customer\Services\CustomerResolver;
use Modules\Customer\Services\MeasurementService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Size Intelligence v2: a customer's saved body-measurement profile (get/save
 * API + service), used to prefill "find my size".
 */
class CustomerMeasurementTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_service_saves_and_reads_profile_dropping_blanks_and_unknowns(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $customer = app(CustomerResolver::class)->forUser($user);
        $service = app(MeasurementService::class);

        $this->assertSame([], $service->for($customer));

        $service->save($customer, ['bust' => 90, 'waist' => 72, 'shoulder' => '', 'junk' => 5]);

        $saved = $service->for($customer->fresh());
        $this->assertSame(90.0, $saved['bust']);
        $this->assertSame(72.0, $saved['waist']);
        $this->assertArrayNotHasKey('shoulder', $saved);   // blank dropped
        $this->assertArrayNotHasKey('junk', $saved);        // unknown ignored
    }

    public function test_save_is_idempotent_one_profile_per_customer(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $customer = app(CustomerResolver::class)->forUser($user);
        $service = app(MeasurementService::class);

        $service->save($customer, ['bust' => 88]);
        $service->save($customer, ['bust' => 91, 'hip' => 95]);

        $this->assertDatabaseCount('customer_measurements', 1);
        $this->assertSame(91.0, $service->for($customer->fresh())['bust']);
    }

    public function test_api_get_and_update_profile(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        // Empty to start.
        $this->actingAs($user)->getJson('/api/v1/customer/measurements')
            ->assertOk()->assertJsonPath('data', []);

        // Save.
        $this->actingAs($user)->putJson('/api/v1/customer/measurements', ['bust' => 90, 'hip' => 96])
            ->assertOk()->assertJsonPath('data.bust', 90);

        // Read back.
        $this->actingAs($user)->getJson('/api/v1/customer/measurements')
            ->assertOk()->assertJsonPath('data.hip', 96);
    }

    public function test_guest_cannot_access_measurements(): void
    {
        $this->getJson('/api/v1/customer/measurements')->assertUnauthorized();
    }

    public function test_update_validates_ranges(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $this->actingAs($user)->putJson('/api/v1/customer/measurements', ['bust' => 9999])
            ->assertUnprocessable();
    }
}

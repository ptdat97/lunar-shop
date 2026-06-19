<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_lists_provinces(): void
    {
        $this->seedLocations();

        $this->getJson('/api/v1/locations/provinces')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name']]])
            ->assertJsonFragment(['name' => 'Thành phố Hồ Chí Minh']);
    }

    public function test_lists_wards_for_a_province(): void
    {
        $hcm = $this->seedLocations();

        $this->getJson("/api/v1/locations/provinces/{$hcm->id}/wards")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Phường Bến Nghé']);
    }

    public function test_unknown_province_returns_404(): void
    {
        $this->getJson('/api/v1/locations/provinces/999999/wards')->assertNotFound();
    }
}

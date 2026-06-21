<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * R4 — every /api/v1/* failure returns one envelope: { message, errors? },
 * regardless of the client's Accept header.
 */
class ApiErrorEnvelopeTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_not_found_returns_message_envelope(): void
    {
        $this->seedBaseData();

        $this->getJson('/api/v1/products/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_error_envelope_without_accept_json_header(): void
    {
        $this->seedBaseData();

        // A headless client that does NOT send Accept: application/json must
        // still get JSON (not an HTML error page) on /api/v1/*.
        $response = $this->get('/api/v1/products/does-not-exist');

        $response->assertNotFound();
        $this->assertJson($response->getContent());
        $response->assertJsonPath('message', 'Resource not found.');
    }

    public function test_validation_still_returns_message_and_errors(): void
    {
        $this->seedBaseData();

        $this->postJson('/api/v1/cart', ['quantity' => 1])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['variant_id']]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * R5 — token (PAT) auth for app/headless clients. Additive: the SPA cookie flow
 * is untouched. A token issued here authenticates the existing `auth:sanctum`
 * endpoints via a Bearer header (no session/cookie).
 */
class TokenAuthTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_issue_token_with_valid_credentials(): void
    {
        $this->seedBaseData();
        User::factory()->create(['email' => 'app@example.com', 'password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'app@example.com',
            'password' => 'password123',
            'device_name' => 'iphone',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token']);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_issue_token_rejects_bad_credentials(): void
    {
        $this->seedBaseData();
        User::factory()->create(['email' => 'app@example.com', 'password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/token', [
            'email' => 'app@example.com',
            'password' => 'wrong',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_returns_token(): void
    {
        $this->seedBaseData();

        $this->postJson('/api/v1/auth/token/register', [
            'name' => 'New App User',
            'email' => 'new@example.com',
            'password' => 'password123',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'email'], 'token'])
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_token_authenticates_protected_endpoint(): void
    {
        $this->seedBaseData();
        User::factory()->create(['email' => 'app@example.com', 'password' => Hash::make('password123')]);

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => 'app@example.com',
            'password' => 'password123',
        ])->json('token');

        // No cookie/session — purely the Bearer token.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/orders')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_revoke_token_invalidates_it(): void
    {
        $this->seedBaseData();
        User::factory()->create(['email' => 'app@example.com', 'password' => Hash::make('password123')]);

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => 'app@example.com',
            'password' => 'password123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/token/revoke')
            ->assertOk()
            ->assertJsonPath('data.status', 'token_revoked');

        // The PAT row is gone.
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());

        // The same token must no longer authenticate. Clear the memoized auth
        // guards first: the previous (authenticated) request leaves the `web`
        // guard holding the resolved user on this shared test app instance, so
        // Sanctum's web-guard fallback would short-circuit before the (now
        // deleted) token is even checked — a harness artifact, not real behaviour.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/orders')
            ->assertUnauthorized();
    }
}

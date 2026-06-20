<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_register_creates_user_and_authenticates(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'jane@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertAuthenticated();
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->createUser(['email' => 'dupe@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Dup', 'email' => 'dupe@example.com', 'password' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrorFor('email');
    }

    public function test_login_with_valid_and_invalid_credentials(): void
    {
        $this->createUser(['email' => 'log@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'log@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.email', 'log@example.com');

        $this->postJson('/api/v1/auth/login', ['email' => 'log@example.com', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrorFor('email');
    }

    public function test_logout_clears_session(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_customer_endpoints_require_auth(): void
    {
        // /customer is public on purpose: the storefront calls it to detect
        // login state, so a guest gets 200 + null (not 401).
        $this->getJson('/api/v1/customer')->assertOk()->assertJsonPath('data', null);

        // Personal data still requires auth.
        $this->getJson('/api/v1/customer/orders')->assertUnauthorized();
    }

    public function test_update_profile(): void
    {
        $user = $this->createUser(['name' => 'Old Name', 'email' => 'old@example.com']);

        $this->actingAs($user)
            ->patchJson('/api/v1/customer', ['name' => 'New Name', 'email' => 'old@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_change_password_requires_correct_current(): void
    {
        $user = $this->createUser(['password' => bcrypt('password123')]);

        $this->actingAs($user)->patchJson('/api/v1/customer/password', [
            'current_password' => 'wrong',
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
        ])->assertStatus(422)->assertJsonValidationErrorFor('current_password');

        $this->actingAs($user)->patchJson('/api/v1/customer/password', [
            'current_password' => 'password123',
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
        ])->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass12', $user->fresh()->password));
    }
}

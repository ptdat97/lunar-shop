<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Modules\Core\Support\Settings;
use Modules\Customer\Filament\Pages\CustomerSettingsPage;
use Modules\Customer\Services\TokenIssuer;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Personal access token policy: expiry, abilities, refresh.
 *
 * Tokens used to be issued with `abilities: ['*']` and no expiry, and nothing
 * checked abilities anywhere — a stolen token worked forever and could do
 * everything. Expiry is stamped per token (`expires_at`) rather than through
 * Sanctum's global `expiration`, which measures from `created_at` and would
 * therefore have invalidated every token already in the wild.
 */
class TokenPolicyTest extends TestCase
{
    use CreatesStorefrontData;

    private function issueToken(): array
    {
        $user = $this->createUser(['password' => bcrypt('secret1234')]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'secret1234',
            'device_name' => 'pixel-8',
        ])->assertOk();

        return [$user, $response];
    }

    public function test_a_new_token_carries_an_expiry_and_reports_it(): void
    {
        [$user, $response] = $this->issueToken();

        $expiresAt = $response->json('token_expires_at');
        $this->assertNotNull($expiresAt, 'the client needs to know when to refresh');

        $stored = $user->tokens()->first();
        $this->assertNotNull($stored->expires_at);
        $this->assertEqualsWithDelta(
            Carbon::now()->addDays(app(TokenIssuer::class)->ttlDays())->timestamp,
            $stored->expires_at->timestamp,
            60,
        );
    }

    public function test_the_saved_ttl_decides_a_new_tokens_expiry(): void
    {
        app(Settings::class)->put('customer', ['ttl_days' => 7]);

        [$user] = $this->issueToken();

        $this->assertEqualsWithDelta(
            Carbon::now()->addDays(7)->timestamp,
            $user->tokens()->first()->expires_at->timestamp,
            60,
        );
    }

    /** `0` is a real choice: kiosk-style clients that must never be signed out. */
    public function test_zero_days_means_no_expiry(): void
    {
        app(Settings::class)->put('customer', ['ttl_days' => 0]);

        [$user] = $this->issueToken();

        $this->assertNull($user->tokens()->first()->expires_at);
    }

    /**
     * Lowering the TTL must not log anyone out: each token carries its own
     * `expires_at`, unlike Sanctum's global `expiration` which measures from
     * `created_at` and would retroactively kill every token in the wild.
     */
    public function test_lowering_the_ttl_leaves_existing_tokens_alone(): void
    {
        app(Settings::class)->put('customer', ['ttl_days' => 90]);
        [$user] = $this->issueToken();
        $original = $user->tokens()->first()->expires_at;

        app(Settings::class)->put('customer', ['ttl_days' => 1]);

        $this->assertTrue($original->equalTo($user->tokens()->first()->fresh()->expires_at));
    }

    public function test_the_ttl_is_clamped(): void
    {
        $issuer = app(TokenIssuer::class);
        $settings = app(Settings::class);

        $settings->put('customer', ['ttl_days' => -5]);
        $this->assertSame(0, $issuer->ttlDays());

        $settings->put('customer', ['ttl_days' => 99999]);
        $this->assertSame(TokenIssuer::MAX_TTL_DAYS, $issuer->ttlDays());
    }

    public function test_it_falls_back_to_config_when_unset(): void
    {
        $this->assertSame(
            (int) config('customer.ttl_days'),
            app(TokenIssuer::class)->ttlDays(),
        );
    }

    public function test_the_admin_page_saves_the_ttl(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $this->actingAs($staff, 'staff');

        Livewire::test(CustomerSettingsPage::class)
            ->fillForm(['ttl_days' => 14])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(14, app(TokenIssuer::class)->ttlDays());
    }

    /** Abilities are a security scope; the page must not expose them. */
    public function test_the_admin_page_does_not_expose_token_abilities(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $this->actingAs($staff, 'staff');

        Livewire::test(CustomerSettingsPage::class)
            ->assertFormFieldExists('ttl_days')
            ->assertFormFieldDoesNotExist('abilities');
    }

    public function test_sanctums_global_expiration_stays_off(): void
    {
        // It compares against `created_at`, so switching it on would retroactively
        // invalidate every token already issued.
        $this->assertNull(config('sanctum.expiration'));
    }

    public function test_a_new_token_is_scoped_to_the_customer_surface(): void
    {
        [$user] = $this->issueToken();

        $token = $user->tokens()->first();

        $this->assertSame(['customer:*'], $token->abilities);
        $this->assertTrue($token->can('customer:*'));
        $this->assertFalse($token->can('pos:*'), 'a customer token must not reach staff surfaces');
    }

    public function test_a_scoped_token_reaches_the_customer_endpoints(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('app', ['customer:*'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/orders')
            ->assertOk();
    }

    public function test_a_token_without_the_ability_is_forbidden(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('pos-terminal', ['pos:*'])->plainTextToken;

        // 403 through the shared error envelope, not a 500 or a silent success.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/orders')
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_a_legacy_wildcard_token_keeps_working(): void
    {
        $user = $this->createUser();

        // Issued before this change: abilities ['*'], no expires_at.
        $token = $user->createToken('legacy', ['*'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/orders')
            ->assertOk();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('stale', ['customer:*'], Carbon::now()->subMinute())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customer/orders')
            ->assertStatus(401);
    }

    public function test_refresh_issues_a_new_token_and_revokes_the_old_one(): void
    {
        $user = $this->createUser();
        $old = $user->createToken('pixel-8', ['customer:*'], Carbon::now()->addDays(60));
        $oldPlain = $old->plainTextToken;

        $fresh = $this->withHeader('Authorization', "Bearer {$oldPlain}")
            ->postJson('/api/v1/auth/token/refresh')
            ->assertOk()
            ->json('token');

        $this->assertNotSame($oldPlain, $fresh);

        // Exactly one token survives, and the device name carries over so the
        // user's token list stays meaningful.
        $this->assertSame('pixel-8', $user->tokens()->sole()->name);

        // A stolen copy of the old token stops working the moment the real
        // client rolls forward. Asserted against the store rather than over HTTP:
        // Sanctum memoizes the resolved user on the container, so a second
        // request inside the same test process would still see the old identity.
        $this->assertNull(PersonalAccessToken::findToken($oldPlain));
        $this->assertNotNull(PersonalAccessToken::findToken($fresh));

        // The fresh token is scoped and dated like any other.
        $issued = PersonalAccessToken::findToken($fresh);
        $this->assertSame(['customer:*'], $issued->abilities);
        $this->assertNotNull($issued->expires_at);
    }

    public function test_the_cookie_session_is_unaffected_by_abilities(): void
    {
        $user = $this->createUser();

        // The storefront authenticates by cookie + CSRF and carries no ability
        // list; EnsureTokenAbility only constrains bearer tokens.
        $this->actingAs($user)->getJson('/api/v1/customer/orders')->assertOk();
    }

    public function test_a_cookie_session_cannot_refresh(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/token/refresh')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Only token clients can refresh.');
    }
}

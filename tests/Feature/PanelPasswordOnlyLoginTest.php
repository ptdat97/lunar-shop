<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Lunar\Core\Models\Staff;
use Lunar\Panel\Auth\EmailTwoFactor;
use Lunar\Panel\Notifications\TwoFactorEmailCode;
use Modules\Core\Auth\UnusedEmailTwoFactor;
use Tests\TestCase;

/**
 * Password-only login for the panel.
 *
 * Lunar 2.0 ships no such path — "Every staff login is a two-step challenge" —
 * and no option to change it, so this is imposed from outside the package. What
 * these cover is that only the *second* factor is gone: the password step, its
 * rate limiter and the session hardening all still stand, and switching the
 * policy back on restores the challenge with nothing to undo.
 */
class PanelPasswordOnlyLoginTest extends TestCase
{
    private const PASSWORD = 'mat-khau-dung-123';

    private function staff(array $attributes = []): Staff
    {
        return Staff::factory()->create([
            'admin' => true,
            'password' => Hash::make(self::PASSWORD),
            ...$attributes,
        ]);
    }

    /** The password step alone — the panel's own controller, untouched. */
    private function submitPassword(Staff $staff, string $password = self::PASSWORD, bool $remember = false)
    {
        return $this->post(route('panel.login.store'), array_filter([
            'email' => $staff->email,
            'password' => $password,
            'remember' => $remember ?: null,
        ]));
    }

    /**
     * A full sign-in, as a browser performs it.
     *
     * Two hops on purpose: the panel's login POST always redirects to the
     * challenge, and the skip stands in for that screen on the next request.
     * The redirect is invisible to a person signing in, and paying it is what
     * keeps the password step — credentials, rate limiter, session keys — as
     * the package wrote it.
     */
    private function login(Staff $staff, string $password = self::PASSWORD, bool $remember = false)
    {
        $this->submitPassword($staff, $password, $remember)
            ->assertRedirect(route('panel.two-factor.challenge'));

        return $this->get(route('panel.two-factor.challenge'));
    }

    public function test_a_correct_password_lands_straight_on_the_dashboard(): void
    {
        config(['staff.require_two_factor' => false]);

        $staff = $this->staff();

        $this->login($staff)->assertRedirect(route('panel.dashboard'));

        $this->assertAuthenticatedAs($staff, 'staff');
    }

    /**
     * The panel redirects to the challenge and this stands in for it, so the
     * screen itself must never render while the policy is off.
     */
    public function test_the_challenge_screen_completes_the_login_instead_of_rendering(): void
    {
        config(['staff.require_two_factor' => false]);

        $staff = $this->staff();

        // Following the panel's own redirect must land on the dashboard, not
        // on a code form.
        $this->login($staff)->assertRedirect(route('panel.dashboard'));

        $this->assertAuthenticatedAs($staff, 'staff');
    }

    /** No code is asked for, so no code is sent. */
    public function test_no_one_time_code_is_emailed(): void
    {
        config(['staff.require_two_factor' => false]);

        Notification::fake();

        $this->login($this->staff())->assertRedirect(route('panel.dashboard'));

        Notification::assertNothingSent();

        $this->assertInstanceOf(UnusedEmailTwoFactor::class, app(EmailTwoFactor::class));
    }

    /**
     * The whole point of standing in for the challenge rather than replacing
     * the login route: the password step is untouched.
     */
    public function test_a_wrong_password_is_still_rejected(): void
    {
        config(['staff.require_two_factor' => false]);

        $staff = $this->staff();

        $this->from(route('panel.login'))
            ->submitPassword($staff, 'mat-khau-sai')
            ->assertSessionHasErrors('email');

        $this->assertGuest('staff');
    }

    /** An unknown email must not reach the skip either. */
    public function test_an_unknown_account_cannot_reach_the_dashboard(): void
    {
        config(['staff.require_two_factor' => false]);

        $this->post(route('panel.login.store'), [
            'email' => 'khong-ton-tai@example.com',
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest('staff');
    }

    /**
     * Visiting the challenge without having passed the password step must do
     * nothing — the skip keys off a session the login controller alone writes.
     */
    public function test_the_challenge_url_alone_authenticates_nobody(): void
    {
        config(['staff.require_two_factor' => false]);

        $this->staff();

        $this->get(route('panel.two-factor.challenge'))->assertRedirect(route('panel.login'));

        $this->assertGuest('staff');
    }

    /** Session fixation: the id a guest arrives with must not survive login. */
    public function test_the_session_is_regenerated_on_login(): void
    {
        config(['staff.require_two_factor' => false]);

        $staff = $this->staff();

        $this->get(route('panel.login'));
        $before = session()->getId();

        $this->login($staff);

        $this->assertNotSame($before, session()->getId());
        $this->assertAuthenticatedAs($staff, 'staff');
    }

    /** "Remember me" must survive the shortcut. */
    public function test_remember_me_still_applies(): void
    {
        config(['staff.require_two_factor' => false]);

        $staff = $this->staff();

        $this->login($staff, remember: true)->assertRedirect(route('panel.dashboard'));

        $this->assertNotNull($staff->fresh()->remember_token);
    }

    /**
     * Both branches of the panel's second factor are skipped, not just the
     * emailed one: a staff member who has enrolled an authenticator also signs
     * in with a password alone. Anything else would leave the feature not doing
     * what it says for exactly the people most likely to notice.
     *
     * Their secret is left untouched, so switching the policy back on asks them
     * for their app again with nothing to re-enrol.
     */
    public function test_an_enrolled_authenticator_is_skipped_too(): void
    {
        config(['staff.require_two_factor' => false]);

        $staff = $this->staff(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->login($staff)->assertRedirect(route('panel.dashboard'));

        $this->assertAuthenticatedAs($staff, 'staff');
        $this->assertSame('JBSWY3DPEHPK3PXP', $staff->fresh()->app_authentication_secret);
    }

    /**
     * Turning the policy back on restores the panel's own two-step login with
     * nothing to undo — the skip simply stops applying.
     */
    public function test_requiring_two_factor_restores_the_challenge(): void
    {
        config(['staff.require_two_factor' => true]);

        Notification::fake();

        $staff = $this->staff();

        $this->submitPassword($staff)->assertRedirect(route('panel.two-factor.challenge'));

        $this->assertGuest('staff');

        Notification::assertSentTo($staff, TwoFactorEmailCode::class);

        $this->assertNotInstanceOf(UnusedEmailTwoFactor::class, app(EmailTwoFactor::class));
    }

    /** An enrolled authenticator is asked for again once the policy is back on. */
    public function test_an_enrolled_authenticator_is_challenged_again_when_required(): void
    {
        config(['staff.require_two_factor' => true]);

        Notification::fake();

        $staff = $this->staff(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->submitPassword($staff)->assertRedirect(route('panel.two-factor.challenge'));

        $this->get(route('panel.two-factor.challenge'))->assertOk();

        $this->assertGuest('staff');

        // TOTP-enrolled staff are never emailed a code.
        Notification::assertNothingSent();
    }

    /** And the challenge screen renders again rather than being stood in for. */
    public function test_the_challenge_renders_when_two_factor_is_required(): void
    {
        config(['staff.require_two_factor' => true]);

        Notification::fake();

        $staff = $this->staff();

        $this->submitPassword($staff);

        $this->get(route('panel.two-factor.challenge'))
            ->assertOk()
            ->assertSee('TwoFactorChallenge', false);

        $this->assertGuest('staff');
    }
}

<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lunar\Panel\PanelManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the panel's two-step login into a password-only one.
 *
 * Lunar 2.0 has no password-only path — "Every staff login is a two-step
 * challenge" — and no setting to change that, so this is a project decision
 * imposed from outside the package rather than a supported option.
 *
 * How it works: the panel's own login POST is left completely alone. It still
 * validates the password, still rate-limits, and still puts the pending staff
 * id in the session before redirecting to the challenge. This intercepts that
 * redirect's destination and finishes the login instead of rendering the
 * second step — the same four lines the challenge controller runs once a code
 * checks out.
 *
 * Nothing is bypassed except the second factor. A wrong password never reaches
 * here, because a wrong password never puts an id in the session.
 *
 * ## Why this shape
 *
 * Overriding the login route would mean re-implementing the password step —
 * credentials, rate limiter, session keys — and re-implementing it wrongly is a
 * far worse outcome than a middleware that only ever runs after the password
 * has already been accepted.
 *
 * ## When upstream moves
 *
 * This leans on three upstream internals: the challenge route's name and the
 * two session keys. If any of them is renamed, this stops matching and the
 * panel's own two-step login comes back. That is the safe direction to fail,
 * and it is why the check is written as "recognise the challenge and finish
 * it" rather than "let everything through".
 */
class SkipPanelTwoFactor
{
    /** The route this stands in for. */
    private const CHALLENGE_ROUTE = 'panel.two-factor.challenge';

    private const PENDING_ID = 'panel.login.id';

    private const PENDING_REMEMBER = 'panel.login.remember';

    public function __construct(protected PanelManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldSkip($request)) {
            return $next($request);
        }

        $guard = Auth::guard($this->manager->guard());
        $user = $guard->getProvider()->retrieveById($request->session()->get(self::PENDING_ID));

        if (! $user) {
            // The staff row went away between the password step and this one.
            // Drop the half-finished login and let the panel start over.
            $request->session()->forget([self::PENDING_ID, self::PENDING_REMEMBER]);

            return redirect()->route('panel.login');
        }

        // Exactly what TwoFactorChallengeController::store() does once a code
        // verifies — including regenerating the session, which is what stops a
        // fixated pre-login session id from surviving into an authenticated one.
        $guard->login($user, (bool) $request->session()->pull(self::PENDING_REMEMBER, false));

        $request->session()->forget(self::PENDING_ID);
        $request->session()->regenerate();

        return redirect()->intended(route('panel.dashboard'));
    }

    /**
     * Only on the challenge screen itself, and only when a password has already
     * been accepted. Anything else — including the challenge's POST — is left
     * to the panel.
     */
    protected function shouldSkip(Request $request): bool
    {
        return ! config('staff.require_two_factor')
            && $request->routeIs(self::CHALLENGE_ROUTE)
            && $request->isMethod('GET')
            && $request->session()->has(self::PENDING_ID);
    }
}

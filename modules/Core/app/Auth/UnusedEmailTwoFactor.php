<?php

namespace Modules\Core\Auth;

use Lunar\Core\Models\Staff;
use Lunar\Panel\Auth\EmailTwoFactor;

/**
 * Stops the panel emailing a six-digit code nobody will be asked for.
 *
 * The panel's login controller sends the code *before* redirecting to the
 * challenge, so by the time SkipPanelTwoFactor finishes the login the mail is
 * already on its way. Bound over the real class while password-only login is
 * on, this makes that send a no-op.
 *
 * Only `send()` is overridden. Verification is left alone on purpose: a code
 * that somehow exists must still be checked properly, and if the policy is
 * switched back on mid-session the binding goes with it.
 */
class UnusedEmailTwoFactor extends EmailTwoFactor
{
    public function send(Staff $staff): bool
    {
        // `true` means "a code is on its way" to the caller. Saying so keeps
        // the panel's own flow intact for anything that still consults it,
        // while nothing is actually sent.
        return true;
    }
}

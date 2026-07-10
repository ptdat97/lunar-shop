<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Support\Settings;

/**
 * Personal access token policy for app / POS clients: how long a token lives,
 * what it may do, and how a client rolls one forward.
 *
 * Lives in a service, not the controller, because it is a business rule and two
 * controllers' worth of endpoints depend on it (issue, register, refresh).
 */
class TokenIssuer
{
    /**
     * Mint a token for the user.
     *
     * Expiry is stamped per token rather than through Sanctum's global
     * `expiration` config: that setting measures from `created_at`, so switching
     * it on would retroactively invalidate every token already in the wild.
     */
    public function issue(User $user, ?string $deviceName = null): NewAccessToken
    {
        return $user->createToken(
            $deviceName ?: 'api',
            $this->abilities(),
            $this->expiry(),
        );
    }

    /**
     * Trade a live token for a fresh one, revoking the old.
     *
     * A stolen copy stops working the moment the real client rolls forward.
     * Returns null when the caller is not a token client — a cookie session has
     * nothing to refresh.
     */
    public function refresh(User $user): ?NewAccessToken
    {
        $current = $user->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            return null;
        }

        $deviceName = $current->name;
        $current->delete();

        return $this->issue($user, $deviceName);
    }

    /**
     * Revoke the token this request authenticated with.
     *
     * @return bool whether a real token was revoked (a cookie session has none)
     */
    public function revokeCurrent(User $user): bool
    {
        $current = $user->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            return false;
        }

        $current->delete();

        return true;
    }

    /** Default token lifetime when the shop hasn't chosen one. */
    public const DEFAULT_TTL_DAYS = 60;

    /** A year. Past this a stolen token is a liability, not a convenience. */
    public const MAX_TTL_DAYS = 365;

    /**
     * How many days a newly issued token lives. `0` disables expiry entirely.
     *
     * Admin-configurable (Admin → Settings → Customers): how often the app makes
     * someone sign in again is a shop decision. Only tokens minted *after* a
     * change are affected — each carries its own `expires_at` — so lowering it
     * never locks out the customers already signed in.
     *
     * `abilities()` deliberately stays in config: it is a security scope, not a
     * shop preference, and widening it from a web form would be a privilege
     * escalation waiting to happen.
     */
    public function ttlDays(): int
    {
        $days = (int) app(Settings::class)
            ->get('customer.ttl_days', self::DEFAULT_TTL_DAYS);

        return max(0, min(self::MAX_TTL_DAYS, $days));
    }

    /** When a newly issued token stops working, or null when TTL is disabled. */
    public function expiry(): ?Carbon
    {
        $days = $this->ttlDays();

        return $days > 0 ? Carbon::now()->addDays($days) : null;
    }

    /**
     * What a customer token may reach. Staff/POS tokens get their own set when
     * those clients arrive; the routes already enforce it.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return (array) config('customer.tokens.abilities.customer', ['customer:*']);
    }
}

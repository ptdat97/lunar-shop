<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

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

    /** When a newly issued token stops working, or null when TTL is disabled. */
    public function expiry(): ?Carbon
    {
        $days = (int) config('customer.tokens.ttl_days', 0);

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

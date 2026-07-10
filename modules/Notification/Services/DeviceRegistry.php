<?php

namespace Modules\Notification\Services;

use App\Models\User;
use Modules\Notification\Models\DeviceToken;

/**
 * Push targets for the mobile app: which device belongs to which user.
 *
 * The business rules live here rather than in the controller (§3/§4): who may
 * claim a token, who may revoke one, and what "already registered" means.
 */
class DeviceRegistry
{
    /**
     * Claim a push token for a user.
     *
     * Idempotent: the app re-registers on every launch. The platform may also
     * hand the same token to a different account after a sign-out, so the token
     * is claimed by whoever registers it last — a token addresses a *device*,
     * not a person, and pushing a stranger's orders to it would be a leak.
     */
    public function register(User $user, string $token, string $platform, ?string $deviceName = null): DeviceToken
    {
        return DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ],
        );
    }

    /**
     * Stop pushing to a device (sign-out).
     *
     * Scoped to the caller: a token they do not own must not be revocable, or
     * anyone could silence anyone else's notifications.
     */
    public function revoke(User $user, string $token): bool
    {
        return (bool) DeviceToken::where('user_id', $user->id)
            ->where('token', $token)
            ->delete();
    }
}

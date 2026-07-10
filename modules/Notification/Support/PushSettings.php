<?php

namespace Modules\Notification\Support;

use Modules\Core\Support\Settings;

/**
 * The one push setting a shop owner actually decides: whether to send at all.
 *
 * Which *driver* delivers the push stays in config — it names a class, is
 * resolved in the provider's `register()` before the database is even reachable,
 * and picking one that isn't installed would break every request. That is a
 * deployment concern, not a shop one.
 *
 * Turning push *off*, however, is something an operator needs at 2am when the
 * provider is melting down and every order is retrying. It must not require a
 * deploy.
 */
class PushSettings
{
    public static function enabled(): bool
    {
        return (bool) app(Settings::class)->get('notification.push_enabled', true);
    }
}

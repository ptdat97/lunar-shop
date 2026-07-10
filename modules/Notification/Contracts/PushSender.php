<?php

namespace Modules\Notification\Contracts;

use Modules\Notification\Data\PushMessage;

/**
 * Delivers a push message to a set of device tokens.
 *
 * The contract sits at a replaceable boundary, for the same reason Catalog's
 * SearchEngine does: swapping FCM for APNs, or for a vendor SDK, must not touch
 * a single caller. Nothing here mentions a provider.
 */
interface PushSender
{
    /**
     * Send to every token; return the tokens the provider rejected as dead, so
     * the caller can prune them.
     *
     * Must not throw for a single bad token — a push failure is never allowed to
     * break the business transaction that triggered it.
     *
     * @param  list<string>  $tokens
     * @return list<string> tokens the provider reported as invalid
     */
    public function send(array $tokens, PushMessage $message): array;
}

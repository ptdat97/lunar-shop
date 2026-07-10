<?php

namespace Modules\Notification\Data;

/**
 * A push payload, independent of any provider's wire format.
 */
class PushMessage
{
    /**
     * @param  array<string, string>  $data  deep-link payload the app acts on
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}
}

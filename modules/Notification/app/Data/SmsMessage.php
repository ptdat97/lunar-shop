<?php

namespace Modules\Notification\Data;

use Modules\Notification\Contracts\SmsSender;

/**
 * One SMS to one recipient.
 *
 * Deliberately provider-agnostic: no sender id, no template id, no unicode flag.
 * Those are per-provider concerns a driver reads from its own config — putting
 * them here would make every caller aware of which gateway is installed, which
 * is exactly what {@see SmsSender} exists to
 * prevent.
 */
class SmsMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $body,
    ) {}
}

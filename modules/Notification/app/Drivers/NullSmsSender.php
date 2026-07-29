<?php

namespace Modules\Notification\Drivers;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Contracts\SmsSender;
use Modules\Notification\Data\SmsMessage;

/**
 * The default: records what *would* have been sent and delivers nothing.
 *
 * Keeps a fresh install from needing SMS credentials to boot, and makes the
 * "SMS on, gateway not configured yet" state harmless rather than fatal.
 */
class NullSmsSender implements SmsSender
{
    public function send(SmsMessage $message): bool
    {
        Log::debug('sms suppressed (no provider configured)', [
            'to' => $message->to,
            'length' => mb_strlen($message->body),
        ]);

        return false;
    }
}

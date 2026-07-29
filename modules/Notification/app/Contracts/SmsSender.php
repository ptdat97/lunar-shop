<?php

namespace Modules\Notification\Contracts;

use Modules\Notification\Data\SmsMessage;

/**
 * Delivers an SMS.
 *
 * Same replaceable boundary as {@see PushSender}: swapping Twilio for a
 * Vietnamese gateway (eSMS, SpeedSMS, Viettel) must not touch a caller. Nothing
 * here names a provider.
 */
interface SmsSender
{
    /**
     * Send one message; return true when the provider accepted it.
     *
     * Must not throw. An SMS failure is never allowed to break the business
     * transaction that triggered it — an order must still be placed when the
     * gateway is down. Drivers log and return false instead.
     */
    public function send(SmsMessage $message): bool;
}

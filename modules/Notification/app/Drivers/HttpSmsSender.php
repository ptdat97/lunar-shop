<?php

namespace Modules\Notification\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Contracts\SmsSender;
use Modules\Notification\Data\SmsMessage;
use Modules\Notification\Support\SmsSettings;

/**
 * Generic HTTP SMS gateway.
 *
 * Vietnamese providers (eSMS, SpeedSMS, Viettel, VNPT) all expose the same
 * shape — a POST with an api key, a destination and a body — but disagree on
 * every field *name*. Rather than ship four near-identical drivers, this one
 * takes the field names from settings, so an operator points it at their
 * provider from the admin page instead of waiting on a deploy.
 *
 * A provider that needs real signing (HMAC, OAuth) is not this driver's job —
 * that is a dedicated class named in `notification.sms.drivers`, which is
 * exactly what the {@see SmsSender} contract exists to allow.
 */
class HttpSmsSender implements SmsSender
{
    /** A gateway that hasn't answered in 10s has failed; the order must not wait. */
    protected const TIMEOUT = 10;

    public function send(SmsMessage $message): bool
    {
        $config = SmsSettings::gateway();

        if (($config['endpoint'] ?? '') === '' || ($config['api_key'] ?? '') === '') {
            Log::warning('sms not sent: gateway is enabled but not configured');

            return false;
        }

        $payload = [
            $config['to_field'] => $message->to,
            $config['body_field'] => $message->body,
        ];

        // Optional: many gateways require a registered sender/brand name.
        if (($config['sender'] ?? '') !== '' && ($config['sender_field'] ?? '') !== '') {
            $payload[$config['sender_field']] = $config['sender'];
        }

        try {
            $request = Http::asJson()->timeout(self::TIMEOUT);

            // Two auth styles cover the field: a bearer header, or the key as a
            // body parameter. Which one is a per-provider fact, so it is a setting.
            $request = ($config['auth'] ?? 'body') === 'bearer'
                ? $request->withToken($config['api_key'])
                : $request;

            if (($config['auth'] ?? 'body') !== 'bearer') {
                $payload[$config['api_key_field']] = $config['api_key'];

                if (($config['api_secret'] ?? '') !== '' && ($config['api_secret_field'] ?? '') !== '') {
                    $payload[$config['api_secret_field']] = $config['api_secret'];
                }
            }

            $response = $request->post($config['endpoint'], $payload);
        } catch (\Throwable $e) {
            // Never propagate: an unreachable gateway must not roll back an order.
            Log::error('sms gateway unreachable', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::error('sms gateway rejected the message', [
                'status' => $response->status(),
                // The body can echo the message and the destination number back;
                // truncate so a provider error does not paste PII into the log.
                'body' => mb_substr((string) $response->body(), 0, 200),
            ]);

            return false;
        }

        return true;
    }
}

<?php

namespace Modules\Core\Support;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;

/**
 * Last gate before an error event leaves this server.
 *
 * `send_default_pii` is off, which stops Sentry attaching the request body,
 * cookies and IP wholesale. It does NOT stop the things that carry customer
 * data by accident, and for a shop those are the dangerous ones:
 *
 * - the URL's query string — a VNPay return lands on
 *   `/payment/vnpay/return?vnp_SecureHash=…&vnp_TxnRef=…`, so the signing hash
 *   and the order reference ride along on any error raised there;
 * - request headers — `Authorization`, `X-Cart-Token`, `Cookie`;
 * - anything the app itself set on the scope earlier.
 *
 * What is deliberately KEPT is the staff/customer id. Without some identity an
 * error report cannot be matched to the person who reported it, and an opaque
 * id is the smallest thing that does the job — no email, no name, no address.
 */
class SentryScrubber
{
    /**
     * Query parameters and header names that must never leave the server.
     *
     * Matched case-insensitively as a SUBSTRING, so `vnp_SecureHash`,
     * `vnp_SecureHashType` and a future `vnp_secure_hash` are all covered by
     * one entry. Over-matching here costs a redacted debugging hint; missing a
     * name costs a credential.
     *
     * @var list<string>
     */
    private const SENSITIVE = [
        'secret', 'password', 'token', 'authorization', 'cookie', 'signature',
        'hash', 'api_key', 'apikey', 'access_key', 'private',
        // Contact details that identify a real customer.
        'email', 'phone', 'contact_', 'address',
        // Gateway-specific: MoMo signs with `signature`, VNPay with
        // `vnp_SecureHash`; both also echo the amount and order reference.
        'vnp_', 'partnercode', 'accesskey',
    ];

    private const REDACTED = '[đã lọc]';

    public static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        return self::scrub($event);
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint): ?Event
    {
        return self::scrub($event);
    }

    private static function scrub(Event $event): Event
    {
        self::scrubRequest($event);
        self::scrubUser($event);

        return $event;
    }

    private static function scrubRequest(Event $event): void
    {
        $request = $event->getRequest();

        if ($request === []) {
            return;
        }

        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = self::scrubUrl($request['url']);
        }

        // Sentry keeps the query string separately from the URL; both have to go.
        if (isset($request['query_string'])) {
            $request['query_string'] = self::REDACTED;
        }

        if (isset($request['headers']) && is_array($request['headers'])) {
            $request['headers'] = self::scrubArray($request['headers']);
        }

        if (isset($request['data']) && is_array($request['data'])) {
            $request['data'] = self::scrubArray($request['data']);
        }

        if (isset($request['cookies'])) {
            $request['cookies'] = self::REDACTED;
        }

        $event->setRequest($request);
    }

    /**
     * Keep the id, drop everything that names a person.
     */
    private static function scrubUser(Event $event): void
    {
        $user = $event->getUser();

        if (! $user instanceof UserDataBag) {
            return;
        }

        $id = $user->getId();

        $event->setUser($id === null ? UserDataBag::createFromUserIdentifier('unknown') : UserDataBag::createFromUserIdentifier($id));
    }

    /**
     * Strip the query string but keep the path — the path is what identifies
     * the failing endpoint, and it carries no customer data.
     */
    private static function scrubUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['query'])) {
            return $url;
        }

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        return $rebuilt.($parts['path'] ?? '').'?'.self::REDACTED;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function scrubArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (self::isSensitive((string) $key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::scrubArray($value);
            }
        }

        return $values;
    }

    private static function isSensitive(string $key): bool
    {
        $key = mb_strtolower($key);

        foreach (self::SENSITIVE as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}

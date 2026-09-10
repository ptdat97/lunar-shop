<?php

namespace Tests\Feature;

use Modules\Core\Support\SentryScrubber;
use Sentry\Event;
use Sentry\UserDataBag;
use Tests\TestCase;

/**
 * Nothing that identifies a customer, and no credential, may leave this server.
 *
 * `send_default_pii => false` stops Sentry attaching the request body, cookies
 * and IP wholesale, and that is where most people stop. It is not enough for a
 * shop: a VNPay return lands on
 * `/payment/vnpay/return?vnp_SecureHash=…&vnp_TxnRef=…`, so an error raised
 * there carries the signing hash in the URL — a field Sentry has no reason to
 * treat as special.
 *
 * These are the cases that actually happen on this shop, asserted against the
 * scrubber rather than trusted to configuration.
 */
class SentryScrubberTest extends TestCase
{
    private function eventWithRequest(array $request): Event
    {
        $event = Event::createEvent();
        $event->setRequest($request);

        return $event;
    }

    public function test_a_payment_callback_url_loses_its_query_string(): void
    {
        $event = SentryScrubber::beforeSend($this->eventWithRequest([
            'url' => 'https://shop.example/payment/vnpay/return?vnp_SecureHash=abc123&vnp_TxnRef=ORD-9',
            'query_string' => 'vnp_SecureHash=abc123&vnp_TxnRef=ORD-9',
        ]), null);

        $request = $event->getRequest();

        $this->assertStringNotContainsString('abc123', $request['url'], 'Chữ ký VNPay lọt ra ngoài trong URL.');
        $this->assertStringNotContainsString('ORD-9', $request['url']);

        // The path is what says which endpoint broke — it must survive.
        $this->assertStringContainsString('/payment/vnpay/return', $request['url']);
        $this->assertStringNotContainsString('abc123', $request['query_string']);
    }

    public function test_credentials_and_contact_details_are_redacted(): void
    {
        $event = SentryScrubber::beforeSend($this->eventWithRequest([
            'headers' => [
                'Authorization' => 'Bearer secret-token',
                'X-Cart-Token' => 'cart-abc',
                'Accept' => 'application/json',
            ],
            'data' => [
                'contact_email' => 'khach@example.com',
                'phone' => '0900000000',
                'shipping' => [
                    'address_line_one' => '12 Nguyễn Huệ',
                    'city' => 'TP. Hồ Chí Minh',
                ],
                'quantity' => 2,
            ],
        ]), null);

        $request = $event->getRequest();

        $this->assertStringNotContainsString('secret-token', json_encode($request));
        $this->assertStringNotContainsString('cart-abc', json_encode($request));
        $this->assertStringNotContainsString('khach@example.com', json_encode($request));
        $this->assertStringNotContainsString('0900000000', json_encode($request));
        $this->assertStringNotContainsString('Nguyễn Huệ', json_encode($request));

        // Non-sensitive fields stay, or the report is useless for debugging.
        $this->assertSame('application/json', $request['headers']['Accept']);
        $this->assertSame(2, $request['data']['quantity']);
    }

    /** An id is enough to match a report to a person; a name and email are not needed. */
    public function test_the_user_keeps_an_id_but_loses_everything_else(): void
    {
        $event = Event::createEvent();
        $event->setUser(
            UserDataBag::createFromUserIdentifier(42)
                ->setEmail('nhanvien@example.com')
                ->setUsername('Dat')
                ->setIpAddress('203.0.113.9'),
        );

        $user = SentryScrubber::beforeSend($event, null)->getUser();

        $this->assertSame('42', (string) $user->getId());
        $this->assertNull($user->getEmail(), 'Email của người dùng lọt sang Sentry.');
        $this->assertNull($user->getUsername());
        $this->assertNull($user->getIpAddress());
    }

    /** The scrubber must never be the thing that breaks error reporting. */
    public function test_an_event_with_no_request_survives(): void
    {
        $event = SentryScrubber::beforeSend(Event::createEvent(), null);

        $this->assertInstanceOf(Event::class, $event);
    }

    /** Nothing is sent anywhere until someone sets a DSN on purpose. */
    public function test_sentry_is_disabled_without_a_dsn(): void
    {
        $this->assertNull(
            config('sentry.dsn'),
            'DSN đã được đặt sẵn trong repo — bật đường truyền dữ liệu ra bên thứ ba phải là quyết định có người bấm nút.',
        );

        $this->assertFalse(
            config('sentry.send_default_pii'),
            'send_default_pii bật lên là gửi IP, cookie và thân request của khách sang bên thứ ba.',
        );
    }
}

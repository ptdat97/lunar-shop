<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Modules\Notification\Channels\PushChannel;
use Modules\Notification\Contracts\PushSender;
use Modules\Notification\Data\PushMessage;
use Modules\Notification\Drivers\NullPushSender;
use Modules\Notification\Models\DeviceToken;
use Modules\Notification\Notifications\OrderStatusChanged;
use Modules\Order\Mail\OrderStatusUpdatedMail;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * In-app + push notifications.
 *
 * Every customer message used to be an email; a mobile app has no inbox to read.
 * These notifications sit *alongside* the existing mailables — the emails still
 * go out unchanged, and they are what reaches a guest buyer, who has no `User`
 * row to attach a notification to.
 */
class NotificationTest extends TestCase
{
    use CreatesStorefrontData;

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'NOTIF-0001',
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ], $attributes));
    }

    public function test_a_status_change_notifies_the_signed_in_buyer(): void
    {
        $this->seedBaseData();
        NotificationFacade::fake();

        $user = $this->createUser();
        $order = $this->order(['user_id' => $user->id, 'status' => 'payment-received']);

        $order->update(['status' => 'dispatched']);

        NotificationFacade::assertSentTo($user, OrderStatusChanged::class, function ($notification) {
            return $notification->order->status === 'dispatched'
                && $notification->previousStatus === 'payment-received';
        });
    }

    public function test_a_guest_order_raises_no_notification(): void
    {
        $this->seedBaseData();
        NotificationFacade::fake();

        // Guest checkout has no User row to notify; the email still goes out.
        $order = $this->order(['user_id' => null]);
        $order->update(['status' => 'dispatched']);

        NotificationFacade::assertNothingSent();
    }

    public function test_the_status_email_still_goes_out_unchanged(): void
    {
        $this->seedBaseData();
        Mail::fake();

        $order = $this->order(['status' => 'payment-received']);
        OrderAddress::factory()->create([
            'order_id' => $order->id, 'type' => 'shipping', 'contact_email' => 'buyer@example.com',
        ]);

        $order->fresh()->update(['status' => 'dispatched']);

        Mail::assertQueued(OrderStatusUpdatedMail::class);
    }

    public function test_payment_statuses_notify_the_app_but_do_not_email(): void
    {
        $this->seedBaseData();
        Mail::fake();
        NotificationFacade::fake();

        $user = $this->createUser();
        $order = $this->order(['user_id' => $user->id, 'status' => 'awaiting-payment']);

        $order->update(['status' => 'payment-received']);

        // The confirmation/paid emails cover this transition; the app has no
        // other channel, so it must still hear about it.
        NotificationFacade::assertSentTo($user, OrderStatusChanged::class);
        Mail::assertNotQueued(OrderStatusUpdatedMail::class);
    }

    public function test_the_notification_is_queued_and_carries_the_locale(): void
    {
        $this->seedBaseData();
        NotificationFacade::fake();

        $user = $this->createUser();
        $order = $this->order(['user_id' => $user->id]);

        app()->setLocale('vi');
        $order->update(['status' => 'dispatched']);

        NotificationFacade::assertSentTo($user, OrderStatusChanged::class, function ($notification) {
            // A queued notification renders on a worker, whose locale is the
            // store default — so the customer's language must travel with it.
            return $notification->locale === 'vi';
        });
    }

    public function test_the_payload_carries_a_localised_status_label(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->order(['user_id' => $user->id, 'status' => 'dispatched']);

        app()->setLocale('vi');
        $payload = (new OrderStatusChanged($order, 'payment-received'))->toDatabase($user);

        $this->assertSame('order.status_changed', $payload['type']);
        $this->assertSame('NOTIF-0001', $payload['reference']);
        $this->assertStringContainsString('Đang giao', $payload['body']);
    }

    public function test_push_reaches_the_registered_devices_and_prunes_dead_tokens(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        DeviceToken::create(['user_id' => $user->id, 'token' => 'live-token', 'platform' => 'ios']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'dead-token', 'platform' => 'android']);

        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(public array &$sent) {}

            public function send(array $tokens, PushMessage $message): array
            {
                $this->sent = $tokens;

                return ['dead-token']; // the provider rejects this one
            }
        });

        $order = $this->order(['user_id' => $user->id]);
        app(PushChannel::class)->send($user, new OrderStatusChanged($order, 'payment-received'));

        $this->assertEqualsCanonicalizing(['live-token', 'dead-token'], $sent);

        // An uninstalled app must not be pushed to forever.
        $this->assertNull(DeviceToken::where('token', 'dead-token')->first());
        $this->assertNotNull(DeviceToken::where('token', 'live-token')->first());
    }

    public function test_a_push_provider_outage_never_breaks_the_caller(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 't', 'platform' => 'ios']);

        $this->app->instance(PushSender::class, new class implements PushSender
        {
            public function send(array $tokens, PushMessage $message): array
            {
                throw new \RuntimeException('FCM is down');
            }
        });

        $order = $this->order(['user_id' => $user->id]);

        // An order was still placed; a dead push provider must not undo that.
        app(PushChannel::class)->send($user, new OrderStatusChanged($order, 'payment-received'));

        $this->assertTrue(true);
    }

    public function test_the_default_push_driver_delivers_nothing(): void
    {
        // No provider is wired; the contract exists so adding one is config-only.
        $this->assertInstanceOf(
            NullPushSender::class,
            app(PushSender::class),
        );
    }
}

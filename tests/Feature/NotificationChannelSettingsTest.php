<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Modules\Core\Support\Settings;
use Modules\Notification\Contracts\SmsSender;
use Modules\Notification\Data\SmsMessage;
use Modules\Notification\Drivers\HttpSmsSender;
use Modules\Notification\Filament\Pages\NotificationSettingsPage;
use Modules\Notification\Services\OrderSmsNotifier;
use Modules\Notification\Support\MailSettings;
use Modules\Notification\Support\SmsSettings;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Admin-configured delivery channels: SMTP and SMS.
 *
 * The shop owner has no shell access, so the mail server and the SMS gateway
 * are edited in the admin and stored in `app_settings`. Two things make that
 * risky enough to be worth pinning down: the stored SMTP values only matter if
 * they reach the *runtime* mail config, and every SMS costs real money, so
 * "send" must be opt-in per status rather than on every transition.
 */
class NotificationChannelSettingsTest extends TestCase
{
    use CreatesStorefrontData;

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'SMS-0001',
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ], $attributes));
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    // ── SMTP ────────────────────────────────────────────────────────────────

    public function test_saved_smtp_credentials_reach_the_runtime_mail_config(): void
    {
        app(Settings::class)->put('notification', [
            'mail_override' => true,
            'mail' => [
                'host' => 'smtp.shop.test',
                'port' => '465',
                'username' => 'postmaster',
                'password' => 's3cret',
                'encryption' => 'ssl',
                'from_address' => 'orders@shop.test',
                'from_name' => 'Shop',
            ],
        ]);

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.shop.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('orders@shop.test', config('mail.from.address'));
        // `ssl` means implicit TLS, which Laravel 11+ spells as the smtps scheme.
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_smtp_settings_are_ignored_until_the_override_is_enabled(): void
    {
        config(['mail.mailers.smtp.host' => 'env-host.test']);

        app(Settings::class)->put('notification', [
            'mail_override' => false,
            'mail' => ['host' => 'admin-host.test'],
        ]);

        MailSettings::apply();

        // A shop that never opted in must keep sending through .env.
        $this->assertSame('env-host.test', config('mail.mailers.smtp.host'));
    }

    public function test_a_half_filled_smtp_form_never_replaces_a_working_config(): void
    {
        config(['mail.mailers.smtp.host' => 'env-host.test']);

        app(Settings::class)->put('notification', [
            'mail_override' => true,
            'mail' => ['username' => 'someone'], // no host
        ]);

        MailSettings::apply();

        $this->assertSame('env-host.test', config('mail.mailers.smtp.host'));
    }

    public function test_saving_the_form_blank_keeps_the_stored_password(): void
    {
        $this->actingAsAdmin();

        app(Settings::class)->put('notification', [
            'mail_override' => true,
            'mail' => ['host' => 'smtp.shop.test', 'password' => 'original-secret'],
        ]);

        // Re-saving without retyping the password is the common case: the form
        // renders secrets blank so they never round-trip through the browser.
        Livewire::test(NotificationSettingsPage::class)
            ->fillForm([
                'mail_override' => true,
                'mail' => ['host' => 'smtp.shop.test', 'password' => ''],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('original-secret', app(Settings::class)->get('notification.mail.password'));
    }

    // ── SMS ─────────────────────────────────────────────────────────────────

    public function test_sms_is_off_by_default(): void
    {
        $this->assertFalse(SmsSettings::enabled());
        $this->assertSame([], SmsSettings::events());
    }

    public function test_the_gateway_defaults_are_flat_strings_on_a_fresh_install(): void
    {
        // Regression: config('notification.sms') is the *driver* map, and
        // Settings::get() falls back to config for anything unsaved. Reading
        // the wrong key handed this the nested `drivers` array.
        $gateway = SmsSettings::gateway();

        foreach ($gateway as $key => $value) {
            $this->assertIsString($value, "gateway key {$key} should be a string");
        }
    }

    public function test_an_sms_is_only_sent_for_the_statuses_the_admin_ticked(): void
    {
        app(Settings::class)->put('notification', [
            'sms_enabled' => true,
            'sms_events' => ['dispatched'],
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        $this->assertTrue(SmsSettings::sendsOn('dispatched'));
        $this->assertFalse(SmsSettings::sendsOn('completed'));
    }

    public function test_no_sms_is_sent_while_the_channel_is_disabled(): void
    {
        $this->seedBaseData();

        app(Settings::class)->put('notification', [
            'sms_enabled' => false,
            'sms_events' => ['dispatched'],
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        $order = $this->order(['status' => 'dispatched']);
        OrderAddress::factory()->create([
            'order_id' => $order->id, 'type' => 'shipping', 'contact_phone' => '0912345678',
        ]);

        $sender = new RecordingSmsSender;
        $sent = (new OrderSmsNotifier($sender))->statusChanged($order->fresh());

        $this->assertFalse($sent);
        $this->assertNull($sender->message);
    }

    public function test_a_local_number_is_normalised_to_e164(): void
    {
        $this->seedBaseData();

        app(Settings::class)->put('notification', [
            'sms_enabled' => true,
            'sms_events' => ['dispatched'],
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        $order = $this->order(['status' => 'dispatched']);
        OrderAddress::factory()->create([
            'order_id' => $order->id, 'type' => 'shipping', 'contact_phone' => '0912 345 678',
        ]);

        $sender = new RecordingSmsSender;
        (new OrderSmsNotifier($sender))->statusChanged($order->fresh());

        // The trunk 0 is replaced by the country code, not appended to it.
        $this->assertSame('+84912345678', $sender->message?->to);
    }

    public function test_a_guest_order_still_gets_an_sms(): void
    {
        $this->seedBaseData();

        app(Settings::class)->put('notification', [
            'sms_enabled' => true,
            'sms_events' => ['dispatched'],
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        // No user_id: the in-app notification cannot reach this buyer at all,
        // which is precisely why SMS is worth having.
        $order = $this->order(['status' => 'dispatched', 'user_id' => null]);
        OrderAddress::factory()->create([
            'order_id' => $order->id, 'type' => 'shipping', 'contact_phone' => '0987654321',
        ]);

        $sender = new RecordingSmsSender;
        $sent = (new OrderSmsNotifier($sender))->statusChanged($order->fresh());

        $this->assertTrue($sent);
        $this->assertStringContainsString('SMS-0001', $sender->message?->body ?? '');
    }

    public function test_an_order_without_a_phone_number_is_skipped(): void
    {
        $this->seedBaseData();

        app(Settings::class)->put('notification', [
            'sms_enabled' => true,
            'sms_events' => ['dispatched'],
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        $order = $this->order(['status' => 'dispatched']);

        $sender = new RecordingSmsSender;

        $this->assertFalse((new OrderSmsNotifier($sender))->statusChanged($order->fresh()));
    }

    // ── The HTTP driver ─────────────────────────────────────────────────────

    public function test_the_http_driver_posts_the_provider_field_names(): void
    {
        Http::fake(['gw.test/*' => Http::response(['status' => 'ok'])]);

        app(Settings::class)->put('notification', [
            'sms_gateway' => [
                'endpoint' => 'https://gw.test/send',
                'api_key' => 'key-123',
                'sender' => 'MYSHOP',
                'auth' => 'body',
                'api_key_field' => 'ApiKey',
                'to_field' => 'Phone',
                'body_field' => 'Content',
                'sender_field' => 'Brandname',
            ],
        ]);

        $sent = (new HttpSmsSender)->send(new SmsMessage('+84912345678', 'Hello'));

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request['ApiKey'] === 'key-123'
            && $request['Phone'] === '+84912345678'
            && $request['Content'] === 'Hello'
            && $request['Brandname'] === 'MYSHOP');
    }

    public function test_the_http_driver_can_authenticate_with_a_bearer_token(): void
    {
        Http::fake(['gw.test/*' => Http::response(['status' => 'ok'])]);

        app(Settings::class)->put('notification', [
            'sms_gateway' => [
                'endpoint' => 'https://gw.test/send',
                'api_key' => 'token-abc',
                'auth' => 'bearer',
            ],
        ]);

        (new HttpSmsSender)->send(new SmsMessage('+84912345678', 'Hello'));

        Http::assertSent(function ($request) {
            // The key travels in the header, never in the body.
            return $request->hasHeader('Authorization', 'Bearer token-abc')
                && ! isset($request['api_key']);
        });
    }

    public function test_a_gateway_outage_never_throws(): void
    {
        // An SMS failure must not roll back the order that triggered it.
        Http::fake(fn () => throw new \RuntimeException('connection refused'));

        app(Settings::class)->put('notification', [
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        $this->assertFalse((new HttpSmsSender)->send(new SmsMessage('+84912345678', 'Hi')));
    }

    public function test_a_rejected_message_is_reported_as_failed(): void
    {
        Http::fake(['gw.test/*' => Http::response(['error' => 'insufficient balance'], 402)]);

        app(Settings::class)->put('notification', [
            'sms_gateway' => ['endpoint' => 'https://gw.test/send', 'api_key' => 'k'],
        ]);

        $this->assertFalse((new HttpSmsSender)->send(new SmsMessage('+84912345678', 'Hi')));
    }

    public function test_an_unconfigured_gateway_sends_nothing(): void
    {
        Http::fake();

        app(Settings::class)->put('notification', ['sms_enabled' => true]);

        $this->assertFalse((new HttpSmsSender)->send(new SmsMessage('+84912345678', 'Hi')));
        Http::assertNothingSent();
    }

    // ── The admin page ──────────────────────────────────────────────────────

    public function test_the_admin_page_saves_the_sms_gateway(): void
    {
        $this->actingAsAdmin();

        Livewire::test(NotificationSettingsPage::class)
            ->fillForm([
                'sms_enabled' => true,
                'sms_events' => ['dispatched', 'completed'],
                'sms' => [
                    'endpoint' => 'https://gw.test/send',
                    'api_key' => 'key-123',
                    'auth' => 'body',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(SmsSettings::enabled());
        $this->assertSame(['dispatched', 'completed'], SmsSettings::events());
        $this->assertSame('key-123', SmsSettings::gateway()['api_key']);
    }

    public function test_saving_the_notification_page_leaves_push_untouched(): void
    {
        $this->actingAsAdmin();

        // One Settings group holds push, mail and SMS, and put() replaces the
        // whole group — a page that forgot a key would silently reset it.
        Livewire::test(NotificationSettingsPage::class)
            ->fillForm(['push_enabled' => true, 'sms_enabled' => true, 'sms' => [
                'endpoint' => 'https://gw.test/send', 'api_key' => 'k',
            ]])
            ->call('save');

        $this->assertTrue(\Modules\Notification\Support\PushSettings::enabled());
    }
}

/**
 * Captures the message instead of sending it.
 */
class RecordingSmsSender implements SmsSender
{
    public ?SmsMessage $message = null;

    public function send(SmsMessage $message): bool
    {
        $this->message = $message;

        return true;
    }
}

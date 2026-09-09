<?php

namespace Modules\Notification\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\SettingsGroup;
use Modules\Core\Support\Settings;
use Modules\Notification\Support\MailSettings;
use Modules\Notification\Support\PushSettings;
use Modules\Notification\Support\SmsSettings;
use Modules\Order\Support\OrderStatus;

/**
 * Outgoing mail, SMS and push.
 *
 * The stored SMS gateway lives under `sms_gateway`, not `sms`: `sms` is taken
 * by the driver map in config, which Settings::get() falls back to. Renaming it
 * would silently start reading the wrong thing — see SmsSettings::gateway().
 *
 * The one field the old page had that is gone is the "send a test SMS" box; it
 * was never stored, only a button's argument, and a settings form is the wrong
 * place for an outbound action.
 */
class NotificationSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'notification';
    }

    public function label(): string
    {
        return __('admin.notification_settings.title');
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function priority(): int
    {
        return 70;
    }

    public function fields(): array
    {
        return [
            Field::toggle('mail_override', __('admin.notification_settings.mail_override'))->default(false),
            Field::text('mail.host', __('admin.notification_settings.mail_host'))
                ->visibleWhen('mail_override', '1', 'true')->width(6),
            Field::number('mail.port', __('admin.notification_settings.mail_port'))
                ->visibleWhen('mail_override', '1', 'true')->width(6),
            Field::text('mail.username', __('admin.notification_settings.mail_username'))
                ->visibleWhen('mail_override', '1', 'true')->width(6),
            Field::secret('mail.password', __('admin.notification_settings.mail_password'))
                ->visibleWhen('mail_override', '1', 'true')->width(6),
            Field::select('mail.encryption', __('admin.notification_settings.mail_encryption'), [
                'tls' => 'STARTTLS (587)',
                'ssl' => 'SSL/TLS (465)',
            ])->visibleWhen('mail_override', '1', 'true')->width(4),
            Field::text('mail.from_address', __('admin.notification_settings.mail_from_address'))
                ->rules('email')->visibleWhen('mail_override', '1', 'true')->width(4),
            Field::text('mail.from_name', __('admin.notification_settings.mail_from_name'))
                ->visibleWhen('mail_override', '1', 'true')->width(4),

            Field::toggle('sms_enabled', __('admin.notification_settings.sms_enabled'))->default(false),
            Field::select('sms_events', __('admin.notification_settings.sms_events'), self::statusOptions())
                ->multiple()->visibleWhen('sms_enabled', '1', 'true'),
            Field::text('sms.endpoint', __('admin.notification_settings.sms_endpoint'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(6),
            Field::select('sms.auth', __('admin.notification_settings.sms_auth'), [
                'body' => __('admin.notification_settings.sms_auth_body'),
                'bearer' => __('admin.notification_settings.sms_auth_bearer'),
            ])->visibleWhen('sms_enabled', '1', 'true')->width(6),
            Field::secret('sms.api_key', __('admin.notification_settings.sms_api_key'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(6),
            Field::secret('sms.api_secret', __('admin.notification_settings.sms_api_secret'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(6),
            Field::text('sms.sender', __('admin.notification_settings.sms_sender'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(4),
            Field::text('sms.to_field', __('admin.notification_settings.sms_to_field'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(4),
            Field::text('sms.body_field', __('admin.notification_settings.sms_body_field'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(4),
            Field::text('sms.api_key_field', __('admin.notification_settings.sms_api_key_field'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(4),
            Field::text('sms.api_secret_field', __('admin.notification_settings.sms_api_secret_field'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(4),
            Field::text('sms.sender_field', __('admin.notification_settings.sms_sender_field'))
                ->visibleWhen('sms_enabled', '1', 'true')->width(4),

            Field::toggle('push_enabled', __('admin.notification_settings.push_enabled'))->default(true),
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        $statuses = [
            OrderStatus::AWAITING_PAYMENT,
            OrderStatus::PAYMENT_OFFLINE,
            OrderStatus::PAYMENT_RECEIVED,
            OrderStatus::DISPATCHED,
            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDED,
        ];

        return array_combine($statuses, array_map(OrderStatus::label(...), $statuses));
    }

    public function values(): array
    {
        $smtp = MailSettings::smtp();
        $sms = SmsSettings::gateway();

        return [
            'mail_override' => MailSettings::overrideEnabled(),
            'mail' => [
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'username' => $smtp['username'],
                'password' => $smtp['password'] ?? '',
                'encryption' => $smtp['encryption'],
                'from_address' => $smtp['from_address'],
                'from_name' => $smtp['from_name'],
            ],
            'sms_enabled' => SmsSettings::enabled(),
            'sms_events' => SmsSettings::events(),
            'sms' => [
                'endpoint' => $sms['endpoint'],
                'api_key' => $sms['api_key'] ?? '',
                'api_secret' => $sms['api_secret'] ?? '',
                'sender' => $sms['sender'],
                'auth' => $sms['auth'],
                'api_key_field' => $sms['api_key_field'],
                'api_secret_field' => $sms['api_secret_field'],
                'to_field' => $sms['to_field'],
                'body_field' => $sms['body_field'],
                'sender_field' => $sms['sender_field'],
            ],
            'push_enabled' => PushSettings::enabled(),
        ];
    }

    public function persist(array $data): void
    {
        $mail = (array) ($data['mail'] ?? []);
        $sms = (array) ($data['sms'] ?? []);

        // put() replaces the whole group, so every key this screen owns is
        // written on every save. Blank values are dropped rather than stored as
        // empty strings: the mail/SMS wrappers treat "configured" as "the key is
        // present", and an empty string would make an unconfigured gateway look
        // ready.
        app(Settings::class)->put('notification', [
            'mail_override' => (bool) ($data['mail_override'] ?? false),
            'mail' => array_filter([
                'host' => (string) ($mail['host'] ?? ''),
                'port' => (string) ($mail['port'] ?? ''),
                'username' => (string) ($mail['username'] ?? ''),
                'password' => (string) ($mail['password'] ?? ''),
                'encryption' => (string) ($mail['encryption'] ?? 'tls'),
                'from_address' => (string) ($mail['from_address'] ?? ''),
                'from_name' => (string) ($mail['from_name'] ?? ''),
            ], fn ($value) => $value !== ''),
            'sms_enabled' => (bool) ($data['sms_enabled'] ?? false),
            'sms_events' => array_values((array) ($data['sms_events'] ?? [])),
            // `sms_gateway`, not `sms`: config('sms') is the driver map that
            // Settings::get() falls back to, so storing under `sms` would have
            // the gateway read the driver list instead.
            'sms_gateway' => array_filter([
                'endpoint' => (string) ($sms['endpoint'] ?? ''),
                'api_key' => (string) ($sms['api_key'] ?? ''),
                'api_secret' => (string) ($sms['api_secret'] ?? ''),
                'sender' => (string) ($sms['sender'] ?? ''),
                'auth' => (string) ($sms['auth'] ?? 'body'),
                'api_key_field' => (string) ($sms['api_key_field'] ?? ''),
                'api_secret_field' => (string) ($sms['api_secret_field'] ?? ''),
                'to_field' => (string) ($sms['to_field'] ?? ''),
                'body_field' => (string) ($sms['body_field'] ?? ''),
                'sender_field' => (string) ($sms['sender_field'] ?? ''),
            ], fn ($value) => $value !== ''),
            'push_enabled' => (bool) ($data['push_enabled'] ?? true),
        ]);
    }
}

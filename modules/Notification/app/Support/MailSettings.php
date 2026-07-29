<?php

namespace Modules\Notification\Support;

use Modules\Core\Support\Settings;

/**
 * SMTP credentials, edited in Admin → Settings → Notifications.
 *
 * Laravel builds its mailers from `config('mail')`, which is read from .env at
 * boot. So storing SMTP in the database is only half the job: the stored values
 * have to be pushed back into the runtime config before the first mailer is
 * resolved, which {@see apply()} does from the service provider's boot().
 *
 * Why allow this at all, when the driver *class* for push deliberately stays in
 * config? Because an SMTP host and password are data, not code. Nothing breaks
 * at boot if they are wrong — the mail just fails — whereas naming a push driver
 * class that isn't installed takes down every request. Different blast radius,
 * different home.
 */
class MailSettings
{
    /**
     * Push the admin-configured SMTP values into the runtime mail config.
     *
     * No-op unless the operator both enabled the override and supplied a host:
     * a half-filled form must never replace a working .env configuration.
     */
    public static function apply(): void
    {
        if (! self::overrideEnabled()) {
            return;
        }

        $smtp = self::smtp();

        if ($smtp['host'] === '') {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => (int) $smtp['port'],
            'mail.mailers.smtp.username' => $smtp['username'] ?: null,
            'mail.mailers.smtp.password' => $smtp['password'] ?: null,
            // Laravel 11+ derives TLS from the scheme: `smtps` forces implicit
            // TLS (port 465), while `smtp` opportunistically upgrades (STARTTLS).
            'mail.mailers.smtp.scheme' => $smtp['encryption'] === 'ssl' ? 'smtps' : 'smtp',
        ]);

        if ($smtp['from_address'] !== '') {
            config(['mail.from.address' => $smtp['from_address']]);
        }

        if ($smtp['from_name'] !== '') {
            config(['mail.from.name' => $smtp['from_name']]);
        }
    }

    public static function overrideEnabled(): bool
    {
        return (bool) app(Settings::class)->get('notification.mail_override', false);
    }

    /**
     * The SMTP fields, every key present, falling back to the .env-driven mail
     * config so the form opens showing what the app is actually using.
     *
     * @return array<string, string>
     */
    public static function smtp(): array
    {
        $stored = (array) (app(Settings::class)->get('notification.mail', []) ?: []);

        $defaults = [
            'host' => (string) config('mail.mailers.smtp.host', ''),
            'port' => (string) config('mail.mailers.smtp.port', 587),
            'username' => (string) config('mail.mailers.smtp.username', ''),
            'password' => (string) config('mail.mailers.smtp.password', ''),
            'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'from_address' => (string) config('mail.from.address', ''),
            'from_name' => (string) config('mail.from.name', ''),
        ];

        return array_map(
            static fn ($value) => (string) $value,
            array_replace($defaults, array_filter($stored, static fn ($v) => $v !== null && $v !== '')),
        );
    }
}

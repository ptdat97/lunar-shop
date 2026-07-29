<?php

namespace Modules\Notification\Filament\Pages;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Support\Settings;
use Modules\Notification\Contracts\SmsSender;
use Modules\Notification\Data\SmsMessage;
use Modules\Notification\Support\MailSettings;
use Modules\Notification\Support\PushSettings;
use Modules\Notification\Support\SmsSettings;
use Modules\Order\Support\OrderStatus;

/**
 * How the shop reaches its customers: email (SMTP), SMS, and push.
 *
 * The split between what lives here and what stays in config is deliberate.
 * Credentials and toggles are *data* — a wrong SMTP password fails a send and
 * nothing more, so an owner may edit them without a deploy. A driver *class*
 * name is code: it is resolved before the database is reachable, and naming one
 * that isn't installed breaks every request. That stays in config.
 */
class NotificationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $slug = 'settings/notifications';

    protected static string $view = 'notification::filament.pages.notification-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.notification_settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.notification_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $smtp = MailSettings::smtp();
        $sms = SmsSettings::gateway();

        $this->form->fill([
            'push_enabled' => PushSettings::enabled(),

            'mail_override' => MailSettings::overrideEnabled(),
            'mail' => [
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'username' => $smtp['username'],
                // Never pre-fill the stored password into a form that round-trips
                // through the browser. Blank means "keep it"; see save().
                'password' => '',
                'encryption' => $smtp['encryption'],
                'from_address' => $smtp['from_address'],
                'from_name' => $smtp['from_name'],
            ],

            'sms_enabled' => SmsSettings::enabled(),
            'sms_events' => SmsSettings::events(),
            'sms' => [
                'endpoint' => $sms['endpoint'],
                'api_key' => '',
                'api_secret' => '',
                'sender' => $sms['sender'],
                'auth' => $sms['auth'],
                'api_key_field' => $sms['api_key_field'],
                'api_secret_field' => $sms['api_secret_field'],
                'to_field' => $sms['to_field'],
                'body_field' => $sms['body_field'],
                'sender_field' => $sms['sender_field'],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.notification_settings.mail_section'))
                    ->description(__('admin.notification_settings.mail_desc'))
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('mail_override')
                            ->label(__('admin.notification_settings.mail_override'))
                            ->helperText(__('admin.notification_settings.mail_override_help'))
                            ->live(),

                        Grid::make(2)
                            // Hiding rather than disabling: these fields are
                            // meaningless while .env is in charge, and showing
                            // populated inputs that do nothing invites the
                            // "I changed it and nothing happened" support ticket.
                            ->visible(fn (Get $get) => (bool) $get('mail_override'))
                            ->schema([
                                TextInput::make('mail.host')
                                    ->label(__('admin.notification_settings.mail_host'))
                                    ->placeholder('smtp.gmail.com')
                                    ->required(fn (Get $get) => (bool) $get('mail_override')),
                                TextInput::make('mail.port')
                                    ->label(__('admin.notification_settings.mail_port'))
                                    ->numeric()
                                    ->placeholder('587'),
                                TextInput::make('mail.username')
                                    ->label(__('admin.notification_settings.mail_username')),
                                TextInput::make('mail.password')
                                    ->label(__('admin.notification_settings.mail_password'))
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    ->helperText(__('admin.notification_settings.secret_keep_help')),
                                Select::make('mail.encryption')
                                    ->label(__('admin.notification_settings.mail_encryption'))
                                    ->options([
                                        'tls' => 'STARTTLS (587)',
                                        'ssl' => 'SSL/TLS (465)',
                                    ])
                                    ->default('tls')
                                    ->selectablePlaceholder(false),
                                TextInput::make('mail.from_address')
                                    ->label(__('admin.notification_settings.mail_from_address'))
                                    ->email(),
                                TextInput::make('mail.from_name')
                                    ->label(__('admin.notification_settings.mail_from_name'))
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make(__('admin.notification_settings.sms_section'))
                    ->description(__('admin.notification_settings.sms_desc'))
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('sms_enabled')
                            ->label(__('admin.notification_settings.sms_enabled'))
                            ->helperText(__('admin.notification_settings.sms_enabled_help'))
                            ->live(),

                        CheckboxList::make('sms_events')
                            ->label(__('admin.notification_settings.sms_events'))
                            ->helperText(__('admin.notification_settings.sms_events_help'))
                            ->options(self::statusOptions())
                            ->columns(2)
                            ->visible(fn (Get $get) => (bool) $get('sms_enabled')),

                        Grid::make(2)
                            ->visible(fn (Get $get) => (bool) $get('sms_enabled'))
                            ->schema([
                                TextInput::make('sms.endpoint')
                                    ->label(__('admin.notification_settings.sms_endpoint'))
                                    ->url()
                                    ->columnSpanFull()
                                    ->required(fn (Get $get) => (bool) $get('sms_enabled')),
                                TextInput::make('sms.api_key')
                                    ->label(__('admin.notification_settings.sms_api_key'))
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    ->helperText(__('admin.notification_settings.secret_keep_help')),
                                TextInput::make('sms.api_secret')
                                    ->label(__('admin.notification_settings.sms_api_secret'))
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    ->helperText(__('admin.notification_settings.secret_keep_help')),
                                Select::make('sms.auth')
                                    ->label(__('admin.notification_settings.sms_auth'))
                                    ->options([
                                        'body' => __('admin.notification_settings.sms_auth_body'),
                                        'bearer' => __('admin.notification_settings.sms_auth_bearer'),
                                    ])
                                    ->default('body')
                                    ->selectablePlaceholder(false)
                                    ->live(),
                                TextInput::make('sms.sender')
                                    ->label(__('admin.notification_settings.sms_sender'))
                                    ->helperText(__('admin.notification_settings.sms_sender_help')),
                            ]),

                        // Field names are a per-provider detail. They have sane
                        // defaults and most operators never open this.
                        Section::make(__('admin.notification_settings.sms_fields_section'))
                            ->description(__('admin.notification_settings.sms_fields_desc'))
                            ->collapsed()
                            ->columns(2)
                            ->visible(fn (Get $get) => (bool) $get('sms_enabled'))
                            ->schema([
                                TextInput::make('sms.to_field')
                                    ->label(__('admin.notification_settings.sms_to_field'))
                                    ->placeholder('to'),
                                TextInput::make('sms.body_field')
                                    ->label(__('admin.notification_settings.sms_body_field'))
                                    ->placeholder('content'),
                                TextInput::make('sms.api_key_field')
                                    ->label(__('admin.notification_settings.sms_api_key_field'))
                                    ->placeholder('api_key')
                                    ->visible(fn (Get $get) => $get('sms.auth') !== 'bearer'),
                                TextInput::make('sms.api_secret_field')
                                    ->label(__('admin.notification_settings.sms_api_secret_field'))
                                    ->placeholder('secret')
                                    ->visible(fn (Get $get) => $get('sms.auth') !== 'bearer'),
                                TextInput::make('sms.sender_field')
                                    ->label(__('admin.notification_settings.sms_sender_field'))
                                    ->placeholder('brandname'),
                            ]),

                        // Not persisted — scratch input for the test button.
                        TextInput::make('test_phone')
                            ->label(__('admin.notification_settings.test_phone'))
                            ->helperText(__('admin.notification_settings.test_phone_help'))
                            ->tel()
                            ->placeholder('0912345678')
                            ->visible(fn (Get $get) => (bool) $get('sms_enabled')),
                    ]),

                Section::make(__('admin.notification_settings.section'))
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('push_enabled')
                            ->label(__('admin.notification_settings.push_enabled'))
                            ->helperText(__('admin.notification_settings.push_enabled_help')),
                    ]),
            ]);
    }

    /**
     * Order statuses an SMS can be attached to, using the same localised labels
     * the rest of the admin shows.
     *
     * @return array<string, string>
     */
    protected static function statusOptions(): array
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

        return array_combine(
            $statuses,
            array_map(OrderStatus::label(...), $statuses),
        );
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(Settings::class);

        $mail = (array) ($data['mail'] ?? []);
        $sms = (array) ($data['sms'] ?? []);

        // `put()` replaces the whole group, so every key this page owns is
        // written on every save — including the secrets the form deliberately
        // renders blank. Blank means "unchanged": re-read the stored value
        // rather than wiping a working credential.
        $stored = [
            'push_enabled' => (bool) ($data['push_enabled'] ?? true),

            'mail_override' => (bool) ($data['mail_override'] ?? false),
            'mail' => array_filter([
                'host' => (string) ($mail['host'] ?? ''),
                'port' => (string) ($mail['port'] ?? ''),
                'username' => (string) ($mail['username'] ?? ''),
                'password' => $this->secret($mail['password'] ?? '', 'notification.mail.password'),
                'encryption' => (string) ($mail['encryption'] ?? 'tls'),
                'from_address' => (string) ($mail['from_address'] ?? ''),
                'from_name' => (string) ($mail['from_name'] ?? ''),
            ], fn ($v) => $v !== ''),

            'sms_enabled' => (bool) ($data['sms_enabled'] ?? false),
            'sms_events' => array_values((array) ($data['sms_events'] ?? [])),
            // `sms_gateway` — `sms` is taken by the driver map in config, which
            // Settings::get() falls back to. See SmsSettings::gateway().
            'sms_gateway' => array_filter([
                'endpoint' => (string) ($sms['endpoint'] ?? ''),
                'api_key' => $this->secret($sms['api_key'] ?? '', 'notification.sms_gateway.api_key'),
                'api_secret' => $this->secret($sms['api_secret'] ?? '', 'notification.sms_gateway.api_secret'),
                'sender' => (string) ($sms['sender'] ?? ''),
                'auth' => (string) ($sms['auth'] ?? 'body'),
                'api_key_field' => (string) ($sms['api_key_field'] ?? ''),
                'api_secret_field' => (string) ($sms['api_secret_field'] ?? ''),
                'to_field' => (string) ($sms['to_field'] ?? ''),
                'body_field' => (string) ($sms['body_field'] ?? ''),
                'sender_field' => (string) ($sms['sender_field'] ?? ''),
            ], fn ($v) => $v !== ''),
        ];

        $settings->put('notification', $stored);

        Notification::make()->title(__('admin.notification_settings.saved'))->success()->send();
    }

    /**
     * A submitted secret, or the one already stored when the field was left
     * blank. Keeps "save the form without retyping every password" working.
     */
    protected function secret(mixed $submitted, string $storedPath): string
    {
        $submitted = (string) $submitted;

        if ($submitted !== '') {
            return $submitted;
        }

        return (string) (app(Settings::class)->get($storedPath, '') ?? '');
    }

    /**
     * Send a test email to the signed-in admin.
     *
     * Saved settings are what gets tested — not the unsaved form — because that
     * is the configuration the shop will actually send with. Testing the form
     * state instead would happily report success for a setup that was never
     * persisted.
     */
    public function sendTestMail(): void
    {
        // Filament's own guard — `auth()` here would be the storefront's.
        $recipient = Filament::auth()->user()?->email;

        if (! $recipient) {
            Notification::make()->title(__('admin.notification_settings.test_no_recipient'))->danger()->send();

            return;
        }

        try {
            MailSettings::apply();

            // Sent inline, not queued: the operator is waiting on this answer,
            // and a queued send would report success even when SMTP is wrong.
            Mail::raw(__('admin.notification_settings.test_mail_body'), function ($message) use ($recipient) {
                $message->to($recipient)->subject(__('admin.notification_settings.test_mail_subject'));
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin.notification_settings.test_failed'))
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('admin.notification_settings.test_sent', ['recipient' => $recipient]))
            ->success()
            ->send();
    }

    /**
     * Text the number in the test field using the *saved* gateway settings.
     *
     * Same reasoning as the mail test: this has to exercise what the shop will
     * really send with, and it costs a real message, so it is an explicit
     * button rather than something that fires on save.
     */
    public function sendTestSms(): void
    {
        $phone = trim((string) ($this->data['test_phone'] ?? ''));

        if ($phone === '') {
            Notification::make()->title(__('admin.notification_settings.test_no_phone'))->danger()->send();

            return;
        }

        if (! SmsSettings::isConfigured()) {
            Notification::make()->title(__('admin.notification_settings.test_sms_unconfigured'))->danger()->send();

            return;
        }

        $sent = app(SmsSender::class)->send(new SmsMessage(
            to: $phone,
            body: __('admin.notification_settings.test_sms_body'),
        ));

        if (! $sent) {
            Notification::make()
                ->title(__('admin.notification_settings.test_failed'))
                ->body(__('admin.notification_settings.test_sms_failed_help'))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('admin.notification_settings.test_sent', ['recipient' => $phone]))
            ->success()
            ->send();
    }
}

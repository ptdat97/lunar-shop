<?php

namespace Modules\Checkout\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Support\Settings;

/**
 * Admin page to configure the online payment gateways (VNPay + MoMo) without
 * editing .env. Keys are stored in app_settings and read via Settings (which
 * falls back to config/env when a field is blank). Leaving a gateway's primary
 * credential empty disables it in checkout.
 */
class PaymentSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $slug = 'settings/payment';

    protected static string $view = 'checkout-admin::filament.payment-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.payment.title');
    }

    public function getTitle(): string
    {
        return __('admin.payment.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $settings = app(Settings::class);

        $this->form->fill([
            'default' => $settings->get('payment.default', 'cod'),
            'vnpay' => [
                'tmn_code' => $settings->get('payment.vnpay.tmn_code'),
                'hash_secret' => $settings->get('payment.vnpay.hash_secret'),
                'payment_url' => $settings->get('payment.vnpay.payment_url'),
                'return_url' => $settings->get('payment.vnpay.return_url'),
            ],
            'momo' => [
                'partner_code' => $settings->get('payment.momo.partner_code'),
                'access_key' => $settings->get('payment.momo.access_key'),
                'secret_key' => $settings->get('payment.momo.secret_key'),
                'endpoint' => $settings->get('payment.momo.endpoint'),
                'return_url' => $settings->get('payment.momo.return_url'),
                'ipn_url' => $settings->get('payment.momo.ipn_url'),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.payment.general'))
                    ->schema([
                        Select::make('default')
                            ->label(__('admin.payment.default'))
                            ->helperText(__('admin.payment.default_help'))
                            ->options([
                                'cod' => __('admin.payment.method_cod'),
                                'bank-transfer' => __('admin.payment.method_bank'),
                                'vnpay' => 'VNPay',
                                'momo' => 'MoMo',
                            ])
                            ->required(),
                    ]),

                Section::make(__('admin.payment.vnpay'))
                    ->description(__('admin.payment.vnpay_desc'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('vnpay.tmn_code')->label('TMN Code'),
                        TextInput::make('vnpay.hash_secret')->label('Hash Secret')->password()->revealable(),
                        TextInput::make('vnpay.payment_url')->label(__('admin.payment.payment_url'))->url()->columnSpanFull(),
                        TextInput::make('vnpay.return_url')->label(__('admin.payment.return_url'))->url()->columnSpanFull(),
                    ]),

                Section::make(__('admin.payment.momo'))
                    ->description(__('admin.payment.momo_desc'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('momo.partner_code')->label('Partner Code'),
                        TextInput::make('momo.access_key')->label('Access Key'),
                        TextInput::make('momo.secret_key')->label('Secret Key')->password()->revealable(),
                        TextInput::make('momo.endpoint')->label(__('admin.payment.endpoint'))->url(),
                        TextInput::make('momo.return_url')->label(__('admin.payment.return_url'))->url()->columnSpanFull(),
                        TextInput::make('momo.ipn_url')->label(__('admin.payment.ipn_url'))->url()->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(Settings::class);

        // Store under the top-level 'payment' group so Settings::get('payment.*')
        // resolves. Merge default + both gateways into one group value.
        $settings->put('payment', [
            'default' => (string) ($data['default'] ?? 'cod'),
            'vnpay' => array_filter((array) ($data['vnpay'] ?? []), fn ($v) => $v !== null),
            'momo' => array_filter((array) ($data['momo'] ?? []), fn ($v) => $v !== null),
        ]);

        Notification::make()->title(__('admin.payment.saved'))->success()->send();
    }
}

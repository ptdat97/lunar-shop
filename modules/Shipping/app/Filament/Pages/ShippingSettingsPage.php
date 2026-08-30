<?php

namespace Modules\Shipping\Filament\Pages;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Support\Settings;
use Modules\Shipping\Services\PickupLocation;

/**
 * Admin page for the default flat shipping rate + free-shipping threshold used
 * when no shipping zone matches (Shipping Zones take priority). Stored in
 * app_settings and read via Settings (falls back to config/shipping.php).
 */
class ShippingSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $slug = 'settings/shipping';

    protected string $view = 'shipping-admin::filament.shipping-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.shipping_settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.shipping_settings.title');
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
            'standard_rate' => (int) $settings->get('shipping.standard_rate', 3000),
            'free_threshold' => (int) $settings->get('shipping.free_threshold', 0),
            'pickup_enabled' => (bool) $settings->get('shipping.pickup_enabled', false),
            'pickup_name' => (string) $settings->get('shipping.pickup_name', ''),
            'pickup_line_one' => (string) $settings->get('shipping.pickup_line_one', ''),
            'pickup_city' => (string) $settings->get('shipping.pickup_city', ''),
            'pickup_state' => (string) $settings->get('shipping.pickup_state', ''),
            'pickup_hours' => (string) $settings->get('shipping.pickup_hours', ''),
            'pickup_instructions' => (string) $settings->get('shipping.pickup_instructions', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.shipping_settings.section'))
                    ->description(__('admin.shipping_settings.section_desc'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('standard_rate')
                            ->label(__('admin.shipping_settings.standard_rate'))
                            ->helperText(__('admin.shipping_settings.standard_rate_help'))
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('free_threshold')
                            ->label(__('admin.shipping_settings.free_threshold'))
                            ->helperText(__('admin.shipping_settings.free_threshold_help'))
                            ->numeric()->minValue(0)->required(),
                    ]),

                Section::make(__('admin.shipping_settings.pickup_section'))
                    ->description(__('admin.shipping_settings.pickup_section_desc'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('pickup_enabled')
                            ->label(__('admin.shipping_settings.pickup_enabled'))
                            ->helperText(__('admin.shipping_settings.pickup_enabled_help'))
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('pickup_name')
                            ->label(__('admin.shipping_settings.pickup_name'))
                            ->maxLength(255),
                        TextInput::make('pickup_line_one')
                            ->label(__('admin.shipping_settings.pickup_line_one'))
                            ->maxLength(255)
                            // Required only once switched on: an empty address is
                            // exactly what PickupLocation::isAvailable() refuses.
                            ->required(fn ($get) => (bool) $get('pickup_enabled')),
                        TextInput::make('pickup_city')
                            ->label(__('admin.shipping_settings.pickup_city'))
                            ->maxLength(255)
                            ->required(fn ($get) => (bool) $get('pickup_enabled')),
                        TextInput::make('pickup_state')
                            ->label(__('admin.shipping_settings.pickup_state'))
                            ->maxLength(255),
                        Textarea::make('pickup_hours')
                            ->label(__('admin.shipping_settings.pickup_hours'))
                            ->rows(2)->maxLength(500)->columnSpanFull(),
                        Textarea::make('pickup_instructions')
                            ->label(__('admin.shipping_settings.pickup_instructions'))
                            ->rows(2)->maxLength(500)->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Settings::put() replaces the WHOLE group, so every key this page owns
        // has to be written on every save — omitting one nulls it.
        $payload = [
            'standard_rate' => (int) ($data['standard_rate'] ?? 0),
            'free_threshold' => (int) ($data['free_threshold'] ?? 0),
            'pickup_enabled' => (bool) ($data['pickup_enabled'] ?? false),
        ];

        foreach (PickupLocation::KEYS as $key) {
            if ($key === 'pickup_enabled') {
                continue;
            }

            $payload[$key] = trim((string) ($data[$key] ?? ''));
        }

        app(Settings::class)->put('shipping', $payload);

        Notification::make()->title(__('admin.shipping_settings.saved'))->success()->send();
    }
}

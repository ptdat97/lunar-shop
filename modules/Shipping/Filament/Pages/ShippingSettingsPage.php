<?php

namespace Modules\Shipping\Filament\Pages;

use App\Support\Settings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin page for the default flat shipping rate + free-shipping threshold used
 * when no shipping zone matches (Shipping Zones take priority). Stored in
 * app_settings and read via Settings (falls back to config/shipping.php).
 */
class ShippingSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $slug = 'settings/shipping';

    protected static string $view = 'shipping-admin::filament.shipping-settings';

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
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
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
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(Settings::class)->put('shipping', [
            'standard_rate' => (int) ($data['standard_rate'] ?? 0),
            'free_threshold' => (int) ($data['free_threshold'] ?? 0),
        ]);

        Notification::make()->title(__('admin.shipping_settings.saved'))->success()->send();
    }
}

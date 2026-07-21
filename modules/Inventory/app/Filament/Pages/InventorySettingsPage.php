<?php

namespace Modules\Inventory\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Support\Settings;
use Modules\Inventory\Services\InventoryService;

/**
 * Admin page for inventory thresholds:
 *
 * - the "low stock" level behind the Stock Overview badge/filter, and
 * - how long an unpaid gateway order may hold its reserved stock before
 *   `orders:expire-abandoned` returns the units.
 *
 * Stored in `app_settings`, read back through {@see InventoryService}.
 */
class InventorySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings/inventory';

    protected static string $view = 'inventory::filament.pages.inventory-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.inventory_settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.inventory_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $inventory = app(InventoryService::class);

        $this->form->fill([
            'low_stock_threshold' => $inventory->lowStockThreshold(),
            'hold_minutes' => $inventory->holdMinutes(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.inventory_settings.section'))
                    ->schema([
                        TextInput::make('low_stock_threshold')
                            ->label(__('admin.inventory_settings.low_stock_threshold'))
                            ->helperText(__('admin.inventory_settings.low_stock_threshold_help'))
                            ->numeric()->minValue(1)->required(),

                        TextInput::make('hold_minutes')
                            ->label(__('admin.inventory_settings.hold_minutes'))
                            ->helperText(__('admin.inventory_settings.hold_minutes_help'))
                            ->numeric()
                            ->minValue(InventoryService::MIN_HOLD_MINUTES)
                            ->maxValue(InventoryService::MAX_HOLD_MINUTES)
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // `put()` replaces the whole group, so every key this page owns must be
        // written on every save — otherwise saving one field erases the other.
        app(Settings::class)->put('inventory', [
            'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? InventoryService::DEFAULT_LOW_THRESHOLD),
            'hold_minutes' => (int) ($data['hold_minutes'] ?? InventoryService::DEFAULT_HOLD_MINUTES),
        ]);

        Notification::make()->title(__('admin.inventory_settings.saved'))->success()->send();
    }
}

<?php

namespace Modules\Inventory\Filament\Pages;

use App\Support\Settings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Inventory\Services\InventoryService;

/**
 * Admin page for inventory thresholds. Currently the "low stock" level used by
 * the Stock Overview badge/filter + InventoryService::lowStock(). Stored in
 * app_settings and read via Settings.
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
        $this->form->fill([
            'low_stock_threshold' => app(InventoryService::class)->lowStockThreshold(),
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
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(Settings::class)->put('inventory', [
            'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? InventoryService::DEFAULT_LOW_THRESHOLD),
        ]);

        Notification::make()->title(__('admin.inventory_settings.saved'))->success()->send();
    }
}

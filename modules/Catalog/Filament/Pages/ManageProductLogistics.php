<?php

namespace Modules\Catalog\Filament\Pages;

use Cartalyst\Converter\Laravel\Facades\Converter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\ProductVariantResource;
use Lunar\Admin\Filament\Resources\ProductVariantResource\Pages\ManageVariantShipping;
use Lunar\Admin\Support\Forms\Components\TextInputSelectAffix;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Models\Contracts\ProductVariant as ProductVariantContract;
use Modules\Catalog\Filament\Resources\ProductResource;

/**
 * "Kho & Vận chuyển" tab: merges Lunar's Identifiers, Inventory and Shipping
 * pages into one form (three sections) for option-less products, all operating
 * on the product's single variant.
 *
 * Reuses the field components from ProductVariantResource and the volume
 * calculation from ManageVariantShipping so units convert exactly like Lunar.
 * SKU is deliberately absent — it stays a single source of truth on the
 * "Chi tiết" tab. Hidden for multi-variant products (shouldRegisterNavigation),
 * where these fields are edited per-variant on the ProductVariantResource.
 */
class ManageProductLogistics extends BaseEditRecord
{
    protected static string $resource = ProductResource::class;

    // Identifiers
    public ?string $gtin = null;

    public ?string $mpn = null;

    public ?string $ean = null;

    // Inventory
    public ?string $stock = null;

    public ?string $backorder = null;

    public ?string $purchasable = null;

    // Shipping
    public bool $shippable = true;

    public ?array $dimensions = [
        'length_value' => 0,
        'length_unit' => 'mm',
        'width_value' => 0,
        'width_unit' => 'mm',
        'height_value' => 0,
        'height_unit' => 'mm',
        'weight_value' => 0,
        'weight_unit' => 'kg',
    ];

    public function getTitle(): string|Htmlable
    {
        return __('admin.editor.logistics');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.editor.logistics');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.editor.logistics');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-shipping') ?? 'heroicon-o-truck';
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return $parameters['record']->variants()->withTrashed()->count() == 1;
    }

    protected function getDefaultHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $variant = $this->getVariant();

        $this->gtin = $variant->gtin;
        $this->mpn = $variant->mpn;
        $this->ean = $variant->ean;

        $this->stock = $variant->stock;
        $this->backorder = $variant->backorder;
        $this->purchasable = $variant->purchasable;

        $this->shippable = $variant->shippable;
        $this->dimensions = [
            ...$this->dimensions,
            ...$variant->only(array_keys($this->dimensions)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $variant = $this->getVariant();

        $variant->update([
            'gtin' => $this->gtin,
            'mpn' => $this->mpn,
            'ean' => $this->ean,
            'stock' => $this->stock,
            'backorder' => $this->backorder,
            'purchasable' => $this->purchasable,
            'shippable' => $this->shippable,
            'volume_unit' => 'l',
            'volume_value' => ManageVariantShipping::getVolume(
                ['value' => $this->dimensions['width_value'], 'unit' => $this->dimensions['width_unit']],
                ['value' => $this->dimensions['length_value'], 'unit' => $this->dimensions['length_unit']],
                ['value' => $this->dimensions['height_value'], 'unit' => $this->dimensions['height_unit']],
            ),
            ...$this->dimensions,
        ]);

        return $record;
    }

    protected function getVariant(): ProductVariantContract
    {
        return $this->getRecord()->variants()->withTrashed()->first();
    }

    public function form(Form $form): Form
    {
        $measurements = Converter::getMeasurements();

        $lengths = collect(array_keys($measurements['length'] ?? []))
            ->mapWithKeys(fn ($value) => [$value => $value]);
        $weights = collect(array_keys($measurements['weight'] ?? []))
            ->mapWithKeys(fn ($value) => [$value => $value]);

        return $form->schema([
            Section::make(__('admin.editor.identifiers'))
                ->description(__('admin.editor.identifiers_hint'))
                ->schema([
                    ProductVariantResource::getGtinFormComponent(),
                    ProductVariantResource::getMpnFormComponent(),
                    ProductVariantResource::getEanFormComponent(),
                ])->columns(3),

            Section::make(__('admin.editor.inventory'))
                ->schema([
                    ProductVariantResource::getStockFormComponent(),
                    ProductVariantResource::getBackorderFormComponent(),
                    ProductVariantResource::getPurchasableFormComponent(),
                ])->columns(3),

            Section::make(__('admin.editor.shipping'))
                ->schema([
                    ProductVariantResource::getShippableFormComponent(),
                    $this->dimensionInput('dimensions.length_value', 'length_unit', $lengths, 'length_value'),
                    $this->dimensionInput('dimensions.width_value', 'width_unit', $lengths, 'width_value'),
                    $this->dimensionInput('dimensions.height_value', 'height_unit', $lengths, 'height_value'),
                    $this->dimensionInput('dimensions.weight_value', 'weight_unit', $weights, 'weight_value'),
                ])->columns(['sm' => 1, 'xl' => 2]),
        ])->statePath('');
    }

    /**
     * A dimension text input with a unit-select affix, mirroring Lunar's
     * shipping form (statePath under `dimensions.*`, unit select alongside).
     */
    protected function dimensionInput(string $path, string $unitField, $units, string $labelKey)
    {
        return TextInputSelectAffix::make($path)
            ->label(__("lunarpanel::productvariant.form.{$labelKey}.label"))
            ->numeric()
            ->select(
                fn () => Select::make($unitField)
                    ->options($units)
                    ->selectablePlaceholder(false)
            );
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}

<?php

namespace Modules\Product\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Modules\Product\Models\ProductMaterial;
use Modules\Product\Models\SizeChart;

/**
 * "Size & Fit" sub-page on Lunar's product editor.
 *
 * The product just *picks* a reusable size chart (managed once under Catalog →
 * Size Charts) and edits Material & Care inline. No relation managers, so there
 * is no deferred Livewire load and no "page expired" (419) on scroll.
 */
class ManageProductSizing extends BaseEditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Size & Fit';

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-shipping') ?? 'heroicon-o-table-cells';
    }

    public static function getNavigationLabel(): string
    {
        return 'Size & Fit';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Size chart')
                ->description('Pick a reusable chart. Manage charts under Catalog → Size Charts.')
                ->icon('heroicon-o-table-cells')
                ->schema([
                    Forms\Components\Select::make('size_chart_id')
                        ->label('Assigned size chart')
                        ->options(fn () => SizeChart::where('active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('No size chart')
                        ->helperText('Drives the storefront size chart and the size recommender.'),
                ]),

            Forms\Components\Section::make('Material & Care')
                ->description('Fabric and care details shown on the product page.')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Forms\Components\TextInput::make('material.material')
                        ->label('Main material')->placeholder('e.g. Cotton, Linen, Wool')->maxLength(255),
                    Forms\Components\TextInput::make('material.composition')
                        ->placeholder('e.g. 95% Cotton, 5% Elastane')->maxLength(255),
                    Forms\Components\Select::make('material.stretch')
                        ->options(['none' => 'No stretch', 'slight' => 'Slight stretch', 'stretchy' => 'Stretchy'])->native(false),
                    Forms\Components\Select::make('material.transparency')
                        ->options(['opaque' => 'Opaque', 'semi' => 'Semi-sheer', 'sheer' => 'Sheer'])->native(false),
                    Forms\Components\TextInput::make('material.fabric_weight')
                        ->label('Fabric weight')->placeholder('e.g. 220 gsm')->maxLength(255),
                    Forms\Components\Select::make('material.lining')
                        ->options(['none' => 'Unlined', 'partial' => 'Partially lined', 'full' => 'Fully lined'])->native(false),
                    Forms\Components\Textarea::make('material.care_instruction')
                        ->label('Care instructions')
                        ->placeholder('e.g. Machine wash cold, do not bleach, iron low')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    /**
     * Load the assigned chart + material into the form state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->getRecord();

        $data['size_chart_id'] = $product->sizeChart()->first()?->id;

        $material = $product->material;
        $data['material'] = $material?->only([
            'material', 'composition', 'stretch', 'transparency',
            'fabric_weight', 'lining', 'care_instruction',
        ]) ?? [];

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * Persist the chart assignment + material, then save the product.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $chartId = $data['size_chart_id'] ?? null;
        $material = $data['material'] ?? [];
        unset($data['size_chart_id'], $data['material']);

        // One chart per product (sync replaces any existing assignment).
        $record->sizeChart()->sync($chartId ? [$chartId] : []);

        if (collect($material)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()) {
            ProductMaterial::updateOrCreate(['product_id' => $record->id], $material);
        }

        return parent::handleRecordUpdate($record, $data);
    }
}

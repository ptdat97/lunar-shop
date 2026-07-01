<?php

namespace Modules\Catalog\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\SizeChart;

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

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-shipping') ?? 'heroicon-o-table-cells';
    }

    public function getTitle(): string
    {
        return __('admin.sizing.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.sizing.title');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.sizing.chart_section'))
                ->description(__('admin.sizing.chart_desc'))
                ->icon('heroicon-o-table-cells')
                ->schema([
                    Forms\Components\Select::make('size_chart_id')
                        ->label(__('admin.sizing.assigned_chart'))
                        ->options(fn () => SizeChart::where('active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder(__('admin.sizing.no_chart'))
                        ->helperText(__('admin.sizing.chart_help')),
                ]),

            Forms\Components\Section::make(__('admin.sizing.material_section'))
                ->description(__('admin.sizing.material_desc'))
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Forms\Components\TextInput::make('material.material')
                        ->label(__('admin.sizing.main_material'))->placeholder('e.g. Cotton, Linen, Wool')->maxLength(255),
                    Forms\Components\TextInput::make('material.composition')
                        ->label(__('admin.sizing.composition'))->placeholder('e.g. 95% Cotton, 5% Elastane')->maxLength(255),
                    Forms\Components\Select::make('material.stretch')
                        ->label(__('admin.sizing.stretch'))
                        ->options(['none' => __('admin.sizing.stretch_none'), 'slight' => __('admin.sizing.stretch_slight'), 'stretchy' => __('admin.sizing.stretch_stretchy')])->native(false),
                    Forms\Components\Select::make('material.transparency')
                        ->label(__('admin.sizing.transparency'))
                        ->options(['opaque' => __('admin.sizing.trans_opaque'), 'semi' => __('admin.sizing.trans_semi'), 'sheer' => __('admin.sizing.trans_sheer')])->native(false),
                    Forms\Components\TextInput::make('material.fabric_weight')
                        ->label(__('admin.sizing.fabric_weight'))->placeholder('e.g. 220 gsm')->maxLength(255),
                    Forms\Components\Select::make('material.lining')
                        ->label(__('admin.sizing.lining'))
                        ->options(['none' => __('admin.sizing.lining_none'), 'partial' => __('admin.sizing.lining_partial'), 'full' => __('admin.sizing.lining_full')])->native(false),
                    Forms\Components\Textarea::make('material.care_instruction')
                        ->label(__('admin.sizing.care'))
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

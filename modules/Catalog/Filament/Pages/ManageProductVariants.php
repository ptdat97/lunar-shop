<?php

namespace Modules\Catalog\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Modules\Catalog\Filament\Resources\ProductResource;
use Modules\Catalog\Services\SkuBuilderService;

/**
 * VaniCommerce-style flexible variant builder, as a sub-page on Lunar's product
 * editor (replaces Lunar's options → variant-matrix widget).
 *
 * Flow, mirroring VaniCommerce's product form:
 *   1. Define `variables` inline — one per axis (Colour, Size, …), each with a
 *      name and a list of values. No shared option catalogue, no migrations.
 *   2. "Generate combinations" builds the Cartesian product into the SKU table,
 *      carrying forward price/stock/images for combinations that already exist
 *      (non-destructive — the pain point of Lunar's matrix rebuild).
 *   3. Edit each SKU's code / price / stock / status inline, then save.
 *
 * Persistence goes through {@see SkuBuilderService} (delete-and-recreate + a
 * synced base Price row), so the Pricing engine and the admin cache never drift.
 */
class ManageProductVariants extends BaseEditRecord
{
    protected static string $resource = ProductResource::class;

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-variants') ?? 'heroicon-o-swatch';
    }

    public function getTitle(): string
    {
        return __('admin.variants.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.variants.title');
    }

    public function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make(__('admin.variants.definition_section'))
                ->description(__('admin.variants.definition_desc'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    Forms\Components\Repeater::make('variables')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\TextInput::make("name.{$locale}")
                                ->label(__('admin.variants.axis_name'))
                                ->placeholder('e.g. Colour, Size')
                                ->required()
                                ->columnSpan(2),
                            // How this axis's values are picked on the storefront:
                            // plain text buttons, colour swatches, or image swatches.
                            Forms\Components\Select::make('display_type')
                                ->label(__('admin.variants.display_type'))
                                ->options([
                                    'text' => __('admin.variants.display_text'),
                                    'color' => __('admin.variants.display_color'),
                                    'image' => __('admin.variants.display_image'),
                                ])
                                ->default('text')
                                ->native(false)
                                ->live(),
                            Forms\Components\Repeater::make('values')
                                ->hiddenLabel()
                                ->schema([
                                    Forms\Components\TextInput::make("name.{$locale}")
                                        ->label(__('admin.variants.value_label'))
                                        ->placeholder('e.g. Black, M')
                                        ->required(),
                                    // Colour swatch: a hex the storefront paints the chip with.
                                    Forms\Components\ColorPicker::make('color')
                                        ->label(__('admin.variants.value_color'))
                                        ->visible(fn (Forms\Get $get) => $get('../../display_type') === 'color'),
                                    // Image swatch: a thumbnail stored on the public media disk.
                                    Forms\Components\FileUpload::make('image')
                                        ->label(__('admin.variants.value_image'))
                                        ->image()
                                        ->disk('media')
                                        ->directory('variant-swatches')
                                        ->visibility('public')
                                        ->visible(fn (Forms\Get $get) => $get('../../display_type') === 'image'),
                                ])
                                ->addActionLabel(__('admin.variants.add_value'))
                                ->minItems(1)
                                ->columnSpanFull()
                                ->grid(3),
                        ])
                        ->columns(3)
                        ->addActionLabel(__('admin.variants.add_axis'))
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['name'][$locale] ?? __('admin.variants.new_axis')),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generate')
                            ->label(__('admin.variants.generate'))
                            ->icon('heroicon-o-squares-plus')
                            ->action('generateCombinations'),
                    ]),
                ]),

            Forms\Components\Section::make(__('admin.variants.skus_section'))
                ->description(__('admin.variants.skus_desc'))
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Forms\Components\Repeater::make('skus')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\Hidden::make('variants'),
                            Forms\Components\Placeholder::make('combo_label')
                                ->label(__('admin.variants.combination'))
                                ->content(fn (Forms\Get $get) => $this->comboLabel($get('variants') ?? [])),
                            Forms\Components\TextInput::make('sku')
                                ->label(__('admin.variants.sku_code'))->required(),
                            Forms\Components\TextInput::make('price')
                                ->label(__('admin.variants.price'))->numeric()->minValue(1)->required()
                                ->helperText(__('admin.variants.price_help')),
                            Forms\Components\TextInput::make('quantity')
                                ->label(__('admin.variants.quantity'))->numeric()->default(0),
                            Forms\Components\Select::make('status')
                                ->label(__('admin.variants.status'))
                                ->options(['published' => __('admin.variants.published'), 'disabled' => __('admin.variants.disabled')])
                                ->default('published')->native(false),
                        ])
                        ->columns(3)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state) => $this->comboLabel($state['variants'] ?? []) ?: ($state['sku'] ?? '')),
                ]),
        ]);
    }

    /**
     * Build the Cartesian product of the currently-entered variables into the
     * SKU repeater, preserving existing per-SKU data by combination.
     */
    public function generateCombinations(): void
    {
        $variables = array_values($this->data['variables'] ?? []);

        $combinations = app(SkuBuilderService::class)->combinations($variables);

        if (empty($combinations)) {
            Notification::make()->warning()->title(__('admin.variants.none_generated'))->send();

            return;
        }

        // Index existing rows (from form state) by their combination so edits in
        // progress survive a regenerate, then merge fresh combos over them.
        $existing = collect($this->data['skus'] ?? [])
            ->keyBy(fn ($row) => implode('-', $row['variants'] ?? []));

        $product = $this->getRecord();

        $this->data['skus'] = collect($combinations)->map(function (array $combo, int $i) use ($existing, $product) {
            $key = implode('-', $combo);
            $prior = $existing->get($key);

            return [
                'variants' => $combo,
                'sku' => $prior['sku'] ?? $this->suggestSku($product, $combo),
                'price' => $prior['price'] ?? 0,
                'quantity' => $prior['quantity'] ?? 0,
                'status' => $prior['status'] ?? 'published',
            ];
        })->all();

        Notification::make()->success()->title(__('admin.variants.generated', ['count' => count($combinations)]))->send();
    }

    /**
     * Seed the form from the product's saved variables + SKU rows.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->getRecord();

        // Swatch images are stored as media ids; turn them back into disk paths
        // so the FileUpload component can preview the saved image.
        $data['variables'] = app(SkuBuilderService::class)
            ->hydrateSwatchPaths($product, $product->variables ?? []);
        $data['skus'] = $product->skus()->orderBy('position')->get()
            ->map(fn ($sku) => [
                'variants' => $sku->variants ?? [],
                'sku' => $sku->sku,
                'price' => (int) $sku->price,
                'quantity' => (int) $sku->quantity,
                'status' => $sku->status,
            ])->all();

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * Persist the variables + SKU rows through the builder service. Surfaces a
     * duplicate/clashing-SKU ValidationException as a Filament error notification
     * rather than a 500.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $variables = array_values($data['variables'] ?? []);
        $skus = array_values($data['skus'] ?? []);

        try {
            app(SkuBuilderService::class)->save($record, $variables, $skus);
        } catch (ValidationException $e) {
            Notification::make()->danger()
                ->title(__('admin.variants.save_failed'))
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->send();

            $this->halt();
        }

        return $record->refresh();
    }

    /**
     * A human label for a value-index combination, from the current variables in
     * form state (e.g. [0,1] → "Black, M").
     *
     * @param  array<int, int>  $combo
     */
    protected function comboLabel(array $combo): string
    {
        $locale = app()->getLocale();
        $variables = $this->data['variables'] ?? [];

        return collect($combo)
            ->map(fn ($valueIndex, $axis) => $variables[$axis]['values'][$valueIndex]['name'][$locale] ?? null)
            ->filter()
            ->implode(', ');
    }

    /**
     * A default SKU code suggestion for a new combination (product id + indexes).
     *
     * @param  array<int, int>  $combo
     */
    protected function suggestSku(Model $product, array $combo): string
    {
        return 'P'.$product->id.'-'.implode('-', $combo);
    }
}

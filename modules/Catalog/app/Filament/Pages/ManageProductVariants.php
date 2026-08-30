<?php

namespace Modules\Catalog\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Modules\Assets\Filament\Forms\MediaPicker;
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

    public function form(Schema $schema): Schema
    {
        $locale = app()->getLocale();

        return $schema->components([
            Section::make(__('admin.variants.definition_section'))
                ->description(__('admin.variants.definition_desc'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    Repeater::make('variables')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make("name.{$locale}")
                                ->label(__('admin.variants.axis_name'))
                                ->placeholder('e.g. Colour, Size')
                                ->required()
                                ->columnSpan(2),
                            // How this axis's values are picked on the storefront:
                            // plain text buttons, colour swatches, or image swatches.
                            Select::make('display_type')
                                ->label(__('admin.variants.display_type'))
                                ->options([
                                    'text' => __('admin.variants.display_text'),
                                    'color' => __('admin.variants.display_color'),
                                    'image' => __('admin.variants.display_image'),
                                ])
                                ->default('text')
                                // Native for the same reason as `status` below —
                                // three options, no need for a JS dropdown.
                                ->live(),
                            Repeater::make('values')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make("name.{$locale}")
                                        ->label(__('admin.variants.value_label'))
                                        ->placeholder('e.g. Black, M')
                                        ->required(),
                                    // Colour swatch: a hex the storefront paints the chip with.
                                    ColorPicker::make('color')
                                        ->label(__('admin.variants.value_color'))
                                        ->visible(fn (Get $get) => $get('../../display_type') === 'color'),
                                    // Image swatch: picked from the shared Media Library
                                    // (modules/Assets) — an Asset id, never a direct upload.
                                    MediaPicker::make('image', type: 'image')
                                        ->label(__('admin.variants.value_image'))
                                        ->visible(fn (Get $get) => $get('../../display_type') === 'image'),
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

                    Actions::make([
                        Action::make('generate')
                            ->label(__('admin.variants.generate'))
                            ->icon('heroicon-o-squares-plus')
                            ->action('generateCombinations'),
                    ]),
                ]),

            Section::make(__('admin.variants.skus_section'))
                ->description(__('admin.variants.skus_desc'))
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Repeater::make('skus')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('variants'),
                            Placeholder::make('combo_label')
                                ->label(__('admin.variants.combination'))
                                ->content(fn (Get $get) => $this->comboLabel($get('variants') ?? [])),
                            TextInput::make('sku')
                                ->label(__('admin.variants.sku_code'))->required(),
                            TextInput::make('price')
                                ->label(__('admin.variants.price'))->numeric()->minValue(1)->required()
                                ->helperText(__('admin.variants.price_help')),
                            TextInput::make('quantity')
                                ->label(__('admin.variants.quantity'))->numeric()->default(0),
                            Select::make('status')
                                ->label(__('admin.variants.status'))
                                ->options(['published' => __('admin.variants.published'), 'disabled' => __('admin.variants.disabled')])
                                // Native on purpose. ->native(false) swaps in Filament's
                                // JS select, which is an Alpine component loaded
                                // lazily through x-load and entangled to
                                // data.skus.<uuid>.status — one per row. On a
                                // 12-SKU product that is 12 async components whose
                                // init races the modal opening, and a failed init
                                // aborts the whole subtree pass, taking the media
                                // picker down with it:
                                //
                                //   Livewire Entangle Error: Livewire property
                                //   ['data.skus.<uuid>.status'] cannot be found
                                //
                                // Two options do not need a searchable dropdown.
                                ->default('published'),
                            // Per-SKU photos: picked from the shared Media Library
                            // (modules/Assets) — a list of Asset ids, never a direct upload.
                            MediaPicker::make('images', type: 'image', multiple: true)
                                ->label(__('admin.variants.sku_images'))
                                ->columnSpanFull(),
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

        // Index existing rows by their combination — keeping the repeater key each
        // one already has — so edits in progress survive a regenerate.
        $existing = collect($this->data['skus'] ?? [])
            ->mapWithKeys(fn ($row, $key) => [
                implode('-', $row['variants'] ?? []) => ['key' => $key, 'row' => $row],
            ]);

        $product = $this->getRecord();

        $this->data['skus'] = collect($combinations)->mapWithKeys(function (array $combo) use ($existing, $product) {
            $match = $existing->get(implode('-', $combo));
            $prior = $match['row'] ?? [];

            // Repeater items are keyed by UUID, and the browser binds to those
            // keys — `data.skus.<uuid>.images`, `.status`, and so on. Writing a
            // plain list here renumbered every row to 0, 1, 2… and left the
            // Alpine bindings pointing at paths that no longer existed:
            //
            //   Livewire Entangle Error: Livewire property
            //   ['data.skus.<uuid>.status'] cannot be found on component
            //
            // The visible casualty was the media picker — choosing an image
            // wrote to a dead path, so it looked like it simply did nothing.
            //
            // Reusing the key an existing row already has is not just about
            // shape: it keeps that row's bindings alive across a regenerate
            // instead of tearing down and rebuilding every one of them.
            $key = $this->isRepeaterKey($match['key'] ?? null)
                ? $match['key']
                : $this->newRepeaterKey();

            return [$key => [
                'variants' => $combo,
                'sku' => $prior['sku'] ?? $this->suggestSku($product, $combo),
                'price' => $prior['price'] ?? 0,
                'quantity' => $prior['quantity'] ?? 0,
                'status' => $prior['status'] ?? 'published',
                'images' => $prior['images'] ?? [],
            ]];
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

        // Swatch/SKU images are stored as Asset ids (MediaPicker's native
        // value) — no hydration needed, the picker resolves ids directly.
        $data['variables'] = $product->variables ?? [];
        $data['skus'] = $product->skus()->orderBy('position')->get()
            ->map(fn ($sku) => [
                'variants' => $sku->variants ?? [],
                'sku' => $sku->sku,
                'price' => (int) $sku->price,
                'quantity' => (int) $sku->quantity,
                'status' => $sku->status,
                'images' => $sku->images ?? [],
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
    /**
     * A key minted the way the SKU repeater mints its own, so state written here
     * is indistinguishable from state Filament produced. Asking the component
     * rather than hardcoding Str::uuid() means a future ->generateUuidUsing()
     * on that repeater is honoured for free.
     */
    protected function newRepeaterKey(): string
    {
        $repeater = $this->getSchemaComponent('form.skus');

        $key = $repeater instanceof Repeater ? $repeater->generateUuid() : null;

        return $key ?? (string) Str::uuid();
    }

    /** Is this an existing repeater key, rather than a stale list index? */
    protected function isRepeaterKey(mixed $key): bool
    {
        return is_string($key) && ! ctype_digit($key);
    }

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

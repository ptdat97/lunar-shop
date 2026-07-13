<?php

namespace Modules\Catalog\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Lunar\Admin\Events\ProductVariantOptionsUpdated;
use Lunar\Admin\Filament\Resources\ProductResource\Widgets\ProductOptionsWidget;
use Lunar\Facades\DB;
use Lunar\Models\Asset;
use Lunar\Models\Contracts\ProductOption as ProductOptionContract;
use Lunar\Models\Contracts\ProductOptionValue as ProductOptionValueContract;
use Lunar\Models\Contracts\ProductVariant as ProductVariantContract;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Modules\Assets\Filament\Forms\MediaPicker;

/**
 * Simplified variant builder: exactly two global options — Size and Color —
 * instead of Lunar's free-form option designer. Sizes are toggle chips; each
 * color carries a hex swatch (stored in ProductOptionValue meta.swatch) and an
 * optional swatch image (media collection 'swatch') for the storefront.
 *
 * The parent widget's save pipeline (permutation mapping, variant create /
 * copy / delete, price + option syncing) is reused untouched; only the option
 * configuration UI and value persistence are replaced.
 */
class SimpleVariantsWidget extends ProductOptionsWidget
{
    use WithFileUploads;

    protected static string $view = 'catalog-admin::filament.widgets.simple-variants';

    /** Pending swatch uploads, keyed by the colour row index. */
    public array $colorImages = [];

    /** Set by the save action so after() doesn't run on validation failure. */
    protected bool $saveSucceeded = false;

    public string $newSize = '';

    public string $newColor = '';

    /** Handle of the global Size option (lunar.products config, forked core). */
    public static function sizeHandle(): string
    {
        return config('lunar.products.options.size_handle', 'size');
    }

    /** Handle of the global Color option (lunar.products config, forked core). */
    public static function colorHandle(): string
    {
        return config('lunar.products.options.color_handle', 'color');
    }

    public function mount()
    {
        $this->ensureGlobalOptions();

        parent::mount();

        $this->injectPoolOptions();
    }

    /**
     * The Size/Color options are global: find-or-create them and force
     * shared=true so the parent's "no variants left" branch detaches them
     * from the product instead of deleting them for everyone.
     */
    protected function ensureGlobalOptions(): void
    {
        $language = Language::getDefault();

        foreach ([
            static::sizeHandle() => __('admin.variants.size_label'),
            static::colorHandle() => __('admin.variants.color_label'),
        ] as $handle => $label) {
            $option = ProductOption::firstOrCreate(['handle' => $handle], [
                'name' => [$language->code => $label],
                'label' => [$language->code => $label],
                'shared' => true,
            ]);

            if (! $option->shared) {
                $option->update(['shared' => true]);
            }
        }
    }

    /**
     * configureBaseOptions() only sees options attached to the product, and —
     * when none of a pool's values are used by this product's variants — it
     * returns the option with an empty value list. Merge the full global
     * Size/Color pools in (missing values disabled) and pin Size before Color.
     */
    protected function injectPoolOptions(): void
    {
        foreach ([static::sizeHandle(), static::colorHandle()] as $handle) {
            $option = ProductOption::with('values')->where('handle', $handle)->first();

            $index = collect($this->configuredOptions)->search(
                fn ($configured) => ($configured['handle'] ?? null) === $handle
            );

            if ($index === false) {
                $this->configuredOptions[] = $this->mapOption($option, []);
                $index = count($this->configuredOptions) - 1;
            }

            $existingIds = collect($this->configuredOptions[$index]['option_values'])
                ->pluck('id')
                ->filter();

            $missing = $option->values
                ->reject(fn ($value) => $existingIds->contains($value->id))
                ->map(fn ($value) => $this->mapOptionValue($value, false));

            $this->configuredOptions[$index]['option_values'] = collect($this->configuredOptions[$index]['option_values'])
                ->concat($missing)
                ->sortBy('position')
                ->values()
                ->toArray();
        }

        $order = [static::sizeHandle() => 0, static::colorHandle() => 1];

        $this->configuredOptions = collect($this->configuredOptions)
            ->sortBy(fn ($option) => $order[$option['handle']] ?? 99)
            ->values()
            ->toArray();
    }

    protected function mapOption(ProductOptionContract $option, array $values = []): array
    {
        $mapped = parent::mapOption($option, $values);

        $mapped['handle'] = $option->handle;
        // Shared options are readonly in the stock widget; ours are edited inline.
        $mapped['readonly'] = false;

        return $mapped;
    }

    protected function mapOptionValue(ProductOptionValueContract $value, bool $enabled = true)
    {
        $mapped = parent::mapOptionValue($value, $enabled);

        // input[type=color] cannot hold an empty value — default unset swatches
        // to black so Livewire never binds '' (browser warns on every render).
        $mapped['hex'] = $value->swatch_color ?: '#000000';
        $mapped['image_url'] = $value->swatchImageUrl() ?: null;
        $mapped['remove_image'] = false;
        $mapped['picked_asset_id'] = null;

        return $mapped;
    }

    /**
     * Per-colour "pick from Media Library" modal. Stores the chosen asset on
     * the row; the file is copied into the swatch collection on save, so
     * cancelling the page discards the pick like any other unsaved edit.
     */
    public function pickSwatchAction(): Action
    {
        return Action::make('pickSwatch')
            ->label(__('admin.variants.pick_from_library'))
            ->link()
            ->modalHeading(__('admin.variants.pick_from_library'))
            ->modalWidth('md')
            ->form([
                MediaPicker::make('asset_id', type: 'image')
                    ->label(__('admin.variants.library_image'))
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $this->applyPickedSwatch(
                    (int) $arguments['optionIndex'],
                    (int) $arguments['valueIndex'],
                    (int) $data['asset_id'],
                );
            });
    }

    protected function applyPickedSwatch(int $optionIndex, int $valueIndex, int $assetId): void
    {
        $asset = Asset::with('file')->find($assetId);
        $media = $asset?->file;

        if (! $media) {
            return;
        }

        $row = &$this->configuredOptions[$optionIndex]['option_values'][$valueIndex];

        $row['picked_asset_id'] = $asset->id;
        $row['image_url'] = $media->hasGeneratedConversion('thumb')
            ? $media->getUrl('thumb')
            : $media->getUrl();
        $row['remove_image'] = false;

        // A library pick supersedes any pending direct upload for this row.
        unset($this->colorImages[$valueIndex]);
    }

    /**
     * The parent maps every option that has a name — including ones where no
     * value is enabled, which permutates to an empty set and wipes the table.
     * Only feed it options that contribute at least one enabled value.
     */
    public function mapVariantPermutations($fillMissing = true): void
    {
        $original = $this->configuredOptions;

        $this->configuredOptions = collect($original)
            ->filter(fn ($option) => $this->enabledValues($option)->isNotEmpty())
            ->values()
            ->toArray();

        parent::mapVariantPermutations($fillMissing);

        $this->configuredOptions = $original;
    }

    public function toggleOptionValue(int $optionIndex, int $valueIndex): void
    {
        $value = $this->configuredOptions[$optionIndex]['option_values'][$valueIndex];

        $this->configuredOptions[$optionIndex]['option_values'][$valueIndex]['enabled'] = ! $value['enabled'];

        $this->mapVariantPermutations();
    }

    public function addSize(): void
    {
        $this->addPoolValue(static::sizeHandle(), $this->newSize);
        $this->newSize = '';
    }

    public function addColor(): void
    {
        $this->addPoolValue(static::colorHandle(), $this->newColor);
        $this->newColor = '';
    }

    /**
     * Unsaved rows (no id yet) can be discarded; existing pool values can only
     * be disabled, since other products may reference them.
     */
    public function removeUnsavedValue(int $optionIndex, int $valueIndex): void
    {
        $value = $this->configuredOptions[$optionIndex]['option_values'][$valueIndex] ?? null;

        if (! $value || ! empty($value['id'])) {
            return;
        }

        unset(
            $this->configuredOptions[$optionIndex]['option_values'][$valueIndex],
            $this->colorImages[$valueIndex],
        );

        $this->configuredOptions[$optionIndex]['option_values'] = array_values(
            $this->configuredOptions[$optionIndex]['option_values']
        );

        $this->mapVariantPermutations();
    }

    public function removeSwatchImage(int $optionIndex, int $valueIndex): void
    {
        $this->configuredOptions[$optionIndex]['option_values'][$valueIndex]['image_url'] = null;
        $this->configuredOptions[$optionIndex]['option_values'][$valueIndex]['remove_image'] = true;
        $this->configuredOptions[$optionIndex]['option_values'][$valueIndex]['picked_asset_id'] = null;

        unset($this->colorImages[$valueIndex]);
    }

    /**
     * Rebuild the permutation table when a value's name or enabled flag changes
     * (the table rows are keyed by value names). Hex edits deliberately don't
     * trigger a rebuild — they'd needlessly discard unsaved SKU/price edits.
     */
    public function updated($name): void
    {
        if (preg_match('/^configuredOptions\.\d+\.option_values\.\d+\.(value|enabled)$/', $name)) {
            $this->mapVariantPermutations();
        }
    }

    /**
     * Replaces the parent implementation: the two options already exist
     * globally and must never be renamed/recreated per product. Values are
     * created when new, renamed in the default language when edited, and
     * empty/no-value options are dropped the way updateConfiguredOptions()
     * would have done.
     */
    protected function storeConfiguredOptions(): void
    {
        $language = Language::getDefault();

        $this->configuredOptions = collect($this->configuredOptions)
            ->map(function ($option) {
                $option['option_values'] = collect($option['option_values'])
                    ->filter(fn ($value) => trim((string) $value['value']) !== '')
                    ->values()
                    ->toArray();

                return $option;
            })
            ->filter(fn ($option) => $this->enabledValues($option)->isNotEmpty())
            ->values()
            ->toArray();

        foreach ($this->configuredOptions as $optionIndex => $option) {
            foreach ($option['option_values'] as $valueIndex => $value) {
                $model = empty($value['id'])
                    ? new ProductOptionValue(['product_option_id' => $option['id']])
                    : ProductOptionValue::find($value['id']);

                $name = collect($model->name ?? [])->toArray();
                $name[$language->code] = trim($value['value']);

                $model->name = $name;
                $model->position = $value['position'];
                $model->save();

                $this->configuredOptions[$optionIndex]['option_values'][$valueIndex]['id'] = $model->id;
            }
        }
    }

    /**
     * Same pipeline as the parent action, with two fixes the stock widget
     * trips over on this catalog: brand-new variants (product had none to
     * copy from) need a tax class, and a missing base price is created
     * instead of dereferencing null.
     */
    public function saveVariantsAction()
    {
        return Action::make('saveVariants')
            ->label(__('admin.variants.save'))
            ->action(function () {
                $this->saveSucceeded = false;

                $missingSku = collect($this->variants)->contains(
                    fn ($variant) => trim((string) ($variant['sku'] ?? '')) === ''
                );

                if ($missingSku) {
                    Notification::make()
                        ->title(__('admin.variants.sku_required'))
                        ->danger()
                        ->send();

                    return;
                }

                DB::beginTransaction();

                $this->storeConfiguredOptions();

                if (! count($this->variants)) {
                    $variant = $this->record->variants()->first();
                    $variant?->values()->detach();

                    $this->record->productOptions()->exclusive()->each(
                        fn (ProductOptionContract $productOption) => $productOption->delete()
                    );

                    $this->record->productOptions()->shared()->detach();

                    if ($variant) {
                        $this->record->variants()
                            ->where('id', '!=', $variant->id)
                            ->get()
                            ->each(
                                fn (ProductVariantContract $other) => $other->delete()
                            );
                    }

                    DB::commit();

                    $this->saveSucceeded = true;

                    Notification::make()->title(
                        __('lunarpanel::productoption.widgets.product-options.notifications.save-variants.success.title')
                    )->success()->send();

                    return;
                }

                $currency = Currency::getDefault();

                foreach ($this->variants as $variantIndex => $variantData) {
                    $variant = new ProductVariant([
                        'product_id' => $this->record->id,
                        'tax_class_id' => TaxClass::getDefault()->id,
                    ]);
                    $basePrice = null;

                    if (! empty($variantData['variant_id'])) {
                        $variant = ProductVariant::find($variantData['variant_id']);
                        $basePrice = $variant->basePrices->first();
                    }

                    if (! empty($variantData['copied_id'])) {
                        $copiedVariant = ProductVariant::find(
                            $variantData['copied_id']
                        );

                        $variant = $copiedVariant->replicate();
                        $variant->save();

                        $copiedPrice = $copiedVariant->basePrices->first();

                        if ($copiedPrice) {
                            $basePrice = $copiedPrice->replicate();
                            $basePrice->priceable_id = $variant->id;
                        }
                    }

                    $variant->sku = $variantData['sku'];
                    $variant->stock = (int) $variantData['stock'];
                    $variant->save();

                    if (! $basePrice) {
                        $basePrice = $variant->prices()->make([
                            'min_quantity' => 1,
                            'currency_id' => $currency->id,
                        ]);
                    }

                    $basePrice->price = (int) bcmul(
                        (string) ((float) $variantData['price']),
                        (string) $basePrice->currency->factor
                    );
                    $basePrice->save();

                    $optionsValues = $this->mapOptionValuesToIds($variantData['values']);

                    $variant->values()->sync($optionsValues);

                    $this->variants[$variantIndex]['variant_id'] = $variant->id;
                }

                $productOptions = collect($this->configuredOptions)
                    ->mapWithKeys(function ($option) {
                        return [
                            $option['id'] => [
                                'position' => $option['position'],
                            ],
                        ];
                    });

                $this->record->productOptions()->sync($productOptions);

                $variantIds = collect($this->variants)->pluck('variant_id');

                $this->record->variants()->whereNotIn('id', $variantIds)
                    ->get()
                    ->each(
                        fn ($variant) => $variant->delete()
                    );

                DB::commit();

                $this->saveSucceeded = true;

                Notification::make()->title(
                    __('lunarpanel::productoption.widgets.product-options.notifications.save-variants.success.title')
                )->success()->send();
            })
            ->after(function () {
                if (! $this->saveSucceeded) {
                    return;
                }

                $this->persistSwatches();

                // Re-read pools so new value ids / swatch URLs are reflected.
                $this->colorImages = [];
                $this->configureBaseOptions();
                $this->injectPoolOptions();

                ProductVariantOptionsUpdated::dispatch($this->record);
            });
    }

    /**
     * Store each colour's hex in meta.swatch and its uploaded image in the
     * 'swatch' media collection. Runs after save, when every value has an id.
     */
    protected function persistSwatches(): void
    {
        $colorOption = collect($this->configuredOptions)->first(
            fn ($option) => ($option['handle'] ?? null) === static::colorHandle()
        );

        foreach ($colorOption['option_values'] ?? [] as $index => $value) {
            if (empty($value['id'])) {
                continue;
            }

            /** @var ProductOptionValue $model */
            $model = ProductOptionValue::find($value['id']);

            $model->swatch_color = $value['hex'] ?: null;
            $model->save();

            $upload = $this->colorImages[$index] ?? null;
            $pickedMedia = ! empty($value['picked_asset_id'])
                ? Asset::with('file')->find($value['picked_asset_id'])?->file
                : null;

            if ($upload) {
                // Collection is singleFile() — addMedia replaces the old image.
                $model->addMedia($upload->getRealPath())
                    ->usingName(trim($value['value']))
                    ->usingFileName(uniqid('swatch_').'.'.$upload->getClientOriginalExtension())
                    ->toMediaCollection(ProductOptionValue::SWATCH_COLLECTION);
            } elseif ($pickedMedia) {
                // Copy (not move) so the library asset stays intact.
                $pickedMedia->copy($model, ProductOptionValue::SWATCH_COLLECTION);
            } elseif (! empty($value['remove_image'])) {
                $model->clearMediaCollection(ProductOptionValue::SWATCH_COLLECTION);
            }
        }
    }

    protected function addPoolValue(string $handle, string $rawName): void
    {
        $name = trim($rawName);

        if ($name === '') {
            return;
        }

        foreach ($this->configuredOptions as $index => $option) {
            if (($option['handle'] ?? null) !== $handle) {
                continue;
            }

            $exists = collect($option['option_values'])->contains(
                fn ($value) => mb_strtolower(trim($value['value'])) === mb_strtolower($name)
            );

            if ($exists) {
                return;
            }

            $this->configuredOptions[$index]['option_values'][] = [
                'id' => null,
                'enabled' => true,
                'value' => $name,
                'position' => count($option['option_values']) + 1,
                'hex' => $handle === static::colorHandle() ? '#000000' : null,
                'image_url' => null,
                'remove_image' => false,
                'picked_asset_id' => null,
            ];

            $this->mapVariantPermutations();

            return;
        }
    }

    protected function enabledValues(array $option): Collection
    {
        return collect($option['option_values'])->filter(
            fn ($value) => $value['enabled'] && trim((string) $value['value']) !== ''
        );
    }
}

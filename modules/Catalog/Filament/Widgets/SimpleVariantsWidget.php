<?php

namespace Modules\Catalog\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Lunar\Admin\Events\ProductVariantOptionsUpdated;
use Lunar\Admin\Filament\Resources\ProductResource\Widgets\ProductOptionsWidget;
use Lunar\Models\Asset;
use Lunar\Models\Contracts\ProductOption as ProductOptionContract;
use Lunar\Models\Contracts\ProductOptionValue as ProductOptionValueContract;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Catalog\Services\Admin\ProductVariantWriter;

/**
 * Fashion variant builder over Lunar's option designer. Two options are seeded
 * globally (Size and Color) and the user can add further shared options; the
 * option whose handle is `colorHandle()` carries a hex swatch (stored in
 * ProductOptionValue meta.swatch) plus an optional swatch image (media
 * collection 'swatch') for the storefront, while every other option is a plain
 * name + value list.
 *
 * This widget owns the option-configuration UI and value/option persistence;
 * the permutation matrix is written by ProductVariantWriter (kept out of the
 * Filament layer). The parent's permutation mapping is reused untouched.
 */
class SimpleVariantsWidget extends ProductOptionsWidget
{
    use WithFileUploads;

    protected static string $view = 'catalog-admin::filament.widgets.simple-variants';

    /** Pending swatch uploads, keyed by the colour row index. */
    public array $colorImages = [];

    /** Set by the save action so after() doesn't run on validation failure. */
    protected bool $saveSucceeded = false;

    /** Pending "add value" text per option, keyed by option index. */
    public array $newValues = [];

    /** Pending "add option" name for the shared-option creator. */
    public string $newOption = '';

    /**
     * Whether the product uses multiple variants. Derived at mount from the
     * record; the toggle flips it in-session, and it's committed on save
     * (off → collapse back to one default variant).
     */
    public bool $multipleEnabled = false;

    /** Bulk-fill bar: which column to apply, and the value to apply. */
    public string $bulkField = 'price';

    public string $bulkValue = '';

    /** Fields the bulk-fill bar can write across every variant row. */
    public const BULK_FIELDS = ['price', 'compare_price', 'cost_price', 'stock', 'weight', 'model', 'status'];

    /** Allowed per-variant statuses (status = a real guard, see CartService). */
    public const STATUSES = ['published', 'disabled'];

    /** Handle of the global Size option (lunar.products config, forked core). */
    public static function sizeHandle(): string
    {
        return config('lunar.products.options.size_handle', 'size');
    }

    /**
     * Handle of the Color option — the only option that carries swatches (hex +
     * image). Other options (Size and any the user creates) are plain.
     */
    public static function colorHandle(): string
    {
        return config('lunar.products.options.color_handle', 'color');
    }

    public function mount()
    {
        $this->ensureGlobalOptions();

        parent::mount();

        $this->injectPoolOptions();

        // Derive the toggle from existing data: a product already carrying real
        // variants / options starts in multi-variant mode.
        $this->multipleEnabled = $this->record->hasVariants
            || $this->record->variants()->count() > 1;
    }

    /**
     * Size and Color are seeded as global shared options so every product
     * starts with the two most common axes. shared=true also means the
     * parent's "no variants left" branch detaches them from the product
     * instead of deleting them for everyone. Users can add further shared
     * options (Material, Style, …) via the "add option" control.
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
     * How many unused pool values to surface per option by default, so a large
     * shared pool (e.g. six colours) doesn't flood the builder. Values the
     * product already uses are always shown on top of this; the admin adds more
     * with the "add value" input.
     */
    public const DEFAULT_POOL_VALUES = 2;

    /**
     * configureBaseOptions() only sees options whose values this product's
     * variants actually use, and drops the rest. For every shared option
     * (Size, Color and any the user added) we surface those in-use values plus
     * up to DEFAULT_POOL_VALUES unused pool values (disabled) as quick toggles.
     * Size/Color are pinned first; the rest keep their pivot position.
     */
    protected function injectPoolOptions(): void
    {
        // Handles to guarantee a row for: the two seeds + every shared option
        // already configured on this product.
        $handles = collect([static::sizeHandle(), static::colorHandle()])
            ->concat(
                collect($this->configuredOptions)
                    ->pluck('handle')
                    ->filter()
            )
            ->unique()
            ->values();

        foreach ($handles as $handle) {
            $option = ProductOption::with('values')->where('handle', $handle)->first();

            if (! $option) {
                continue;
            }

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

            $merged = collect($this->configuredOptions[$index]['option_values'])
                ->concat($missing)
                ->sortBy('position')
                ->values();

            // Collapse a large pool: keep every value the product actually uses
            // (enabled), plus at most DEFAULT_POOL_VALUES unused ones as quick
            // toggles. configureBaseOptions() surfaces the whole shared pool
            // (all six colours) — this trims the disabled tail.
            $used = $merged->filter(fn ($value) => $value['enabled']);
            $unused = $merged->reject(fn ($value) => $value['enabled'])
                ->take(self::DEFAULT_POOL_VALUES);

            $this->configuredOptions[$index]['option_values'] = $used
                ->concat($unused)
                ->sortBy('position')
                ->values()
                ->toArray();
        }

        // Size, then Color, then everything else by attach order.
        $order = [static::sizeHandle() => 0, static::colorHandle() => 1];

        $this->configuredOptions = collect($this->configuredOptions)
            ->sortBy(fn ($option) => $order[$option['handle'] ?? ''] ?? 99)
            ->values()
            ->toArray();
    }

    /** True when this option carries swatches (hex + image) — only Color. */
    protected function isColorOption(array $option): bool
    {
        return ($option['handle'] ?? null) === static::colorHandle();
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
     * Per-variant image picker: choose one or more images from THIS product's
     * gallery (not the whole library) to attach to a variant row, and mark one
     * primary. Assignments are held on the row and written to the
     * media_product_variant pivot on save (ProductVariantWriter::syncImages).
     */
    public function pickVariantImageAction(): Action
    {
        return Action::make('pickVariantImage')
            ->label(__('admin.variants.pick_image'))
            ->link()
            ->modalHeading(__('admin.variants.pick_image'))
            ->modalWidth('lg')
            ->fillForm(fn (array $arguments) => [
                'image_ids' => $this->variants[$arguments['rowIndex']]['image_ids'] ?? [],
                'primary_image_id' => $this->variants[$arguments['rowIndex']]['primary_image_id'] ?? null,
            ])
            ->form([
                CheckboxList::make('image_ids')
                    ->label(__('admin.variants.pick_image'))
                    ->options(fn () => $this->productImageOptions())
                    ->columns(2)
                    ->bulkToggleable(),
                Select::make('primary_image_id')
                    ->label(__('admin.variants.primary_image'))
                    ->options(fn () => $this->productImageOptions())
                    ->native(false),
            ])
            ->action(function (array $data, array $arguments) {
                $index = $arguments['rowIndex'];
                $ids = array_map('intval', $data['image_ids'] ?? []);

                $this->variants[$index]['image_ids'] = $ids;

                // Keep primary consistent with the selection.
                $primary = (int) ($data['primary_image_id'] ?? 0);
                $this->variants[$index]['primary_image_id'] = in_array($primary, $ids, true)
                    ? $primary
                    : ($ids[0] ?? null);
            });
    }

    /**
     * The product's gallery images as [media id => label] for the picker.
     *
     * @return array<int, string>
     */
    protected function productImageOptions(): array
    {
        return $this->record->getMedia(config('lunar.media.collection', 'images'))
            ->mapWithKeys(fn ($media) => [$media->id => $media->name ?: "#{$media->id}"])
            ->all();
    }

    /**
     * The primary image url for a variant row (for the small table thumbnail).
     */
    public function rowImageUrl(int $rowIndex): ?string
    {
        $id = $this->variants[$rowIndex]['primary_image_id']
            ?? ($this->variants[$rowIndex]['image_ids'][0] ?? null);

        if (! $id) {
            return null;
        }

        $media = $this->record->getMedia(config('lunar.media.collection', 'images'))
            ->firstWhere('id', (int) $id);

        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl();
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

        $this->enrichVariantRows();
    }

    /**
     * The parent builds each permutation row with only sku/price/stock/values;
     * add the builder's extra columns. Rows tied to a saved variant are filled
     * from the DB (model, cost/compare price, weight, status, images); brand-new
     * rows get sensible defaults. Only saved rows are overwritten, so unsaved
     * edits survive a rebuild — the same contract the parent uses for sku/price.
     */
    protected function enrichVariantRows(): void
    {
        $variants = $this->record->variants()
            ->with(['basePrices', 'images'])
            ->get()
            ->keyBy('id');

        foreach ($this->variants as $index => $row) {
            $variant = ! empty($row['variant_id']) ? $variants->get($row['variant_id']) : null;

            if ($variant) {
                $basePrice = $variant->basePrices->first();
                $this->variants[$index] += [
                    'model' => $variant->model,
                    'compare_price' => $basePrice?->compare_price?->decimal,
                    'cost_price' => $variant->cost_price !== null
                        ? bcdiv((string) $variant->cost_price, (string) ($basePrice?->currency->factor ?: 100), 4)
                        : null,
                    'weight' => $variant->weight_value,
                    'status' => $variant->status ?: 'published',
                    'image_ids' => $variant->images->pluck('id')->all(),
                    'primary_image_id' => $variant->images->firstWhere('pivot.primary', true)?->id
                        ?? $variant->images->first()?->id,
                ];

                continue;
            }

            // New permutation row — fill only keys the parent didn't set.
            $this->variants[$index] += [
                'model' => '',
                'compare_price' => '',
                'cost_price' => '',
                'weight' => '',
                'status' => 'published',
                'image_ids' => [],
                'primary_image_id' => null,
            ];
        }
    }

    public function toggleOptionValue(int $optionIndex, int $valueIndex): void
    {
        $value = $this->configuredOptions[$optionIndex]['option_values'][$valueIndex];

        $this->configuredOptions[$optionIndex]['option_values'][$valueIndex]['enabled'] = ! $value['enabled'];

        $this->mapVariantPermutations();
    }

    /** Add a value to the option at $optionIndex from its pending input. */
    public function addValueToOption(int $optionIndex): void
    {
        $handle = $this->configuredOptions[$optionIndex]['handle'] ?? null;
        $name = trim($this->newValues[$optionIndex] ?? '');

        if (! $handle || $name === '') {
            return;
        }

        $this->addPoolValue($handle, $name);
        $this->newValues[$optionIndex] = '';
    }

    /**
     * Create a new shared option (Material, Style, …) from a free-text name and
     * attach it to the builder. Shared so it can be reused across products,
     * matching how Size/Color work. A brand-new option starts with no values —
     * the user adds them inline. Handle is a slug of the name; an existing
     * option with that handle is reused instead of duplicated.
     */
    public function addOption(): void
    {
        $name = trim($this->newOption);

        if ($name === '') {
            return;
        }

        $handle = Str::slug($name);

        if ($handle === '') {
            return;
        }

        // Already on the builder? Ignore.
        $exists = collect($this->configuredOptions)->contains(
            fn ($option) => ($option['handle'] ?? null) === $handle
        );

        if ($exists) {
            $this->newOption = '';

            return;
        }

        $language = Language::getDefault();

        $option = ProductOption::with('values')->firstOrCreate(['handle' => $handle], [
            'name' => [$language->code => $name],
            'label' => [$language->code => $name],
            'shared' => true,
        ]);

        if (! $option->shared) {
            $option->update(['shared' => true]);
        }

        $this->configuredOptions[] = $this->mapOption(
            $option,
            $option->values->map(fn ($value) => $this->mapOptionValue($value, false))->toArray()
        );

        $this->newOption = '';
        $this->mapVariantPermutations();
    }

    /**
     * Drop an option from this product's builder. Shared options (including the
     * two seeds and any created here) stay in the database for other products;
     * this only detaches by removing the row — the sync on save reflects it.
     */
    public function removeOptionFromProduct(int $optionIndex): void
    {
        unset($this->configuredOptions[$optionIndex]);
        $this->configuredOptions = array_values($this->configuredOptions);

        $this->mapVariantPermutations();
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
        // Rebuild the permutation table when a value name/toggle OR an option
        // name changes — the table rows are keyed by option/value names.
        if (preg_match('/^configuredOptions\.\d+\.(value|option_values\.\d+\.(value|enabled))$/', $name)) {
            $this->mapVariantPermutations();
        }
    }

    /**
     * Persist options and their values. Options are normally shared and already
     * exist (Size/Color seeds, plus ones created via addOption()); if one has no
     * id yet it is created as a shared option (name/label/handle from its
     * free-text name) before its values are written. Values are created when new
     * and renamed in the default language when edited; empty/no-value options
     * are dropped the way the parent's updateConfiguredOptions() would.
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
            // Materialise an option that doesn't exist yet (shared).
            if (empty($option['id'])) {
                $name = trim((string) ($option['value'] ?? ''));
                $handle = Str::slug($name) ?: 'option-'.($optionIndex + 1);

                $optionModel = ProductOption::firstOrCreate(['handle' => $handle], [
                    'name' => [$language->code => $name],
                    'label' => [$language->code => $name],
                    'shared' => true,
                ]);

                $option['id'] = $optionModel->id;
                $this->configuredOptions[$optionIndex]['id'] = $optionModel->id;
                $this->configuredOptions[$optionIndex]['handle'] = $optionModel->handle;
            }

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
     * Validate the permutation table before save. Returns the first error
     * message, or null when every row is fine: SKU must be present, SKUs must
     * be unique across rows (the DB column isn't unique, so a clash would
     * silently ship two variants sharing a SKU), and price must be a
     * non-negative number.
     */
    protected function validateVariants(): ?string
    {
        $seen = [];

        foreach ($this->variants as $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));

            if ($sku === '') {
                return __('admin.variants.sku_required');
            }

            $key = mb_strtolower($sku);

            if (isset($seen[$key])) {
                return __('admin.variants.sku_duplicate', ['sku' => $sku]);
            }

            $seen[$key] = true;

            // price / compare_price / cost_price must be non-negative numbers.
            foreach (['price', 'compare_price', 'cost_price'] as $field) {
                $value = str_replace(',', '', (string) ($variant[$field] ?? ''));

                if ($value !== '' && (! is_numeric($value) || (float) $value < 0)) {
                    return __('admin.variants.price_invalid');
                }
            }

            if (! in_array($variant['status'] ?? 'published', self::STATUSES, true)) {
                return __('admin.variants.status_invalid');
            }
        }

        return null;
    }

    /**
     * Apply the bulk-fill value to $bulkField across every variant row. stock
     * casts to int, status snaps to a valid value, everything else is stored
     * verbatim (the writer converts prices to minor units). Blank is a no-op.
     */
    public function applyBulkFill(): void
    {
        $field = in_array($this->bulkField, self::BULK_FIELDS, true) ? $this->bulkField : 'price';
        $value = trim($this->bulkValue);

        if ($value === '') {
            return;
        }

        $value = match ($field) {
            'stock' => (int) $value,
            'status' => in_array($value, self::STATUSES, true) ? $value : 'published',
            default => $value,
        };

        foreach (array_keys($this->variants) as $index) {
            $this->variants[$index][$field] = $value;
        }

        $this->bulkValue = '';
    }

    /**
     * Collapse the product back to a single default variant when the "multiple
     * variants" toggle is turned off. Runs the writer's empty-matrix branch.
     */
    protected function disableVariants(): void
    {
        app(ProductVariantWriter::class)->save($this->record, [], []);
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

                // Toggle off: collapse to a single default variant and stop.
                if (! $this->multipleEnabled) {
                    $this->disableVariants();
                    $this->saveSucceeded = true;

                    Notification::make()->title(
                        __('lunarpanel::productoption.widgets.product-options.notifications.save-variants.success.title')
                    )->success()->send();

                    return;
                }

                if ($error = $this->validateVariants()) {
                    Notification::make()->title($error)->danger()->send();

                    return;
                }

                // Persist options first (back-fills ids into $configuredOptions),
                // then hand the pure DB write to the service, resolving each
                // permutation's option-value ids up front.
                $this->storeConfiguredOptions();

                $variants = collect($this->variants)
                    ->map(fn ($row) => [
                        ...$row,
                        'value_ids' => $this->mapOptionValuesToIds($row['values']),
                    ])
                    ->all();

                $this->variants = app(ProductVariantWriter::class)
                    ->save($this->record, $variants, $this->configuredOptions);

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

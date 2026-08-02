<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Lunar\Base\Purchasable;
use Lunar\Base\Traits\HasPrices;
use Lunar\Models\Asset;
use Lunar\Models\Contracts\TaxClass as TaxClassContract;
use Lunar\Models\Product;
use Lunar\Models\TaxClass;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * VaniCommerce-style flexible SKU. Each row is one Cartesian combination of the
 * product's free-form `variables` (referenced positionally by the `variants`
 * index array) and carries its own price / stock / images.
 *
 * A ProductSku IS a Lunar {@see Purchasable} — it replaces ProductVariant as
 * the thing carts and orders point at, so Lunar's cart-calculate, order-line
 * and discount pipelines keep working unchanged: they only ever call the
 * interface, never a concrete class.
 *
 * It is also a priceable (via {@see HasPrices}): the authoritative prices the
 * Lunar Pricing engine reads live in `lunar_prices` (priceable_type =
 * product_sku); the `price`/`origin_price`/`cost_price` columns are the admin
 * cache that SkuBuilderService keeps in sync with a base Price row.
 *
 * @property int $id
 * @property int $product_id
 * @property ?int $tax_class_id
 * @property ?array<int, int> $variants indexes into products.variables
 * @property int $position
 * @property ?array<int, int> $images Media Library Asset ids (picked via MediaPicker)
 * @property string $model
 * @property string $sku
 * @property int $price
 * @property int $origin_price
 * @property ?int $cost_price
 * @property int $quantity
 * @property ?float $weight
 * @property bool $is_default
 * @property string $status
 */
class ProductSku extends Model implements Purchasable
{
    use HasPrices;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'variants' => 'array',
        'images' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * The table name honours the Lunar prefix so the whole schema stays
     * consistent (`lunar_product_skus`).
     */
    public function getTable(): string
    {
        return config('lunar.database.table_prefix').'product_skus';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::modelClass());
    }

    // ---------------------------------------------------------------------
    // Purchasable contract
    // ---------------------------------------------------------------------

    public function getPrices(): Collection
    {
        return $this->prices;
    }

    public function getUnitQuantity(): int
    {
        return 1;
    }

    public function getTaxClass(): TaxClassContract
    {
        return $this->taxClass ?? TaxClass::getDefault();
    }

    public function getTaxReference(): ?string
    {
        return null;
    }

    /**
     * All SKUs in this shop are physical goods (the storefront is fashion).
     */
    public function getType(): string
    {
        return 'physical';
    }

    /**
     * The product name — the cart/order line description. The variant label
     * (colour / size) is carried separately via getOption().
     */
    public function getDescription(): string
    {
        return $this->product->translateAttribute('name');
    }

    /**
     * A single, human-readable label for this SKU's combination, e.g.
     * "Black, M". Built positionally from the product's `variables` blob and
     * this row's `variants` indexes, in the current locale.
     */
    public function getOption(): ?string
    {
        $options = $this->getOptions();

        return $options->isEmpty() ? null : $options->join(', ');
    }

    /**
     * The per-variable value labels for this SKU, in variable order, localised.
     * Empty for a simple (single) product with no defined variables.
     *
     * @return Collection<int, string>
     */
    public function getOptions(): Collection
    {
        $variables = $this->product->variables ?? [];
        $indexes = $this->variants ?? [];
        $locale = app()->getLocale();

        return collect($variables)
            ->map(function ($variable, $i) use ($indexes, $locale) {
                $valueIndex = $indexes[$i] ?? null;

                if ($valueIndex === null) {
                    return null;
                }

                $value = $variable['values'][$valueIndex] ?? null;

                if ($value === null) {
                    return null;
                }

                // Names are {locale: string} maps; fall back to the first
                // available translation so a missing locale never blanks out.
                $names = $value['name'] ?? [];

                return $names[$locale] ?? (is_array($names) ? reset($names) : $names) ?: null;
            })
            ->filter()
            ->values();
    }

    /**
     * Stable key for this SKU's positional variant combination, e.g. [0,1] =>
     * "0-1". The storefront uses this to jump straight from selected swatches
     * to the matching SKU instead of scanning every SKU on each click.
     */
    public function variantKey(): string
    {
        return static::variantKeyFor($this->variants ?? []);
    }

    /**
     * @param  array<int, int|string>  $combo
     */
    public static function variantKeyFor(array $combo): string
    {
        return collect($combo)
            ->map(fn ($index) => (string) (int) $index)
            ->implode('-');
    }

    /**
     * The option NAME → VALUE pairs for this SKU's combination, localised, in
     * variable order — e.g. [['option' => 'Color', 'value' => 'Black'], …].
     * This is the shape the storefront variant picker groups on; empty for a
     * simple SKU with no variables.
     *
     * @return array<int, array{option: string, value: string}>
     */
    public function optionPairs(): array
    {
        $variables = $this->product->variables ?? [];
        $indexes = $this->variants ?? [];
        $locale = app()->getLocale();

        $localise = function ($names) use ($locale) {
            if (! is_array($names)) {
                return (string) $names;
            }

            return (string) ($names[$locale] ?? reset($names) ?: '');
        };

        $pairs = [];

        foreach ($variables as $i => $variable) {
            $valueIndex = $indexes[$i] ?? null;
            if ($valueIndex === null) {
                continue;
            }

            $optionName = $localise($variable['name'] ?? []);
            $valueName = $localise($variable['values'][$valueIndex]['name'] ?? []);

            if ($optionName !== '' && $valueName !== '') {
                $pairs[] = ['option' => $optionName, 'value' => $valueName];
            }
        }

        return $pairs;
    }

    /**
     * The stable identifier carts/orders record. Because saving variants is
     * delete-and-recreate (ids are not stable across product edits), the `sku`
     * string is the durable handle — not the surrogate id.
     */
    public function getIdentifier(): string
    {
        return $this->sku;
    }

    public function isShippable(): bool
    {
        return true;
    }

    /**
     * The first of this SKU's own images (a Media Library Asset id, picked via
     * MediaPicker), resolved to its Media model; otherwise falls back to the
     * product thumbnail.
     */
    public function getThumbnail(): ?Media
    {
        $first = collect($this->images ?? [])->first();

        if (is_numeric($first)) {
            return Asset::find($first)?->file ?: $this->product->thumbnail;
        }

        return $this->product->thumbnail;
    }

    /**
     * Stock check. SKUs use a plain on-hand quantity (no Lunar backorder/always
     * modes); a non-published SKU can never be fulfilled.
     */
    public function canBeFulfilledAtQuantity(int $quantity): bool
    {
        if ($this->status === 'disabled') {
            return false;
        }

        return $quantity <= $this->getTotalInventory();
    }

    /**
     * Units a shopper can still buy.
     *
     * `quantity` is what is physically in the stockroom and `committed` is the
     * part of it already sold but not yet dispatched, so the sellable figure is
     * the difference. Purchasable checks and the storefront must use this, not
     * `quantity`, or units already promised to another order get sold twice.
     */
    public function getTotalInventory(): int
    {
        return max(0, (int) $this->quantity - (int) $this->committed);
    }
}

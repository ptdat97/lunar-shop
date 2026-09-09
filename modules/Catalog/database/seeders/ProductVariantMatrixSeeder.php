<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\Core\Contracts\Actions\Products\AdjustsStock;
use Lunar\Core\Contracts\Actions\Products\GeneratesProductVariants;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Location;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;

/**
 * Builds the colour x size variant matrix for the demo catalog.
 *
 * Goes through Lunar's own `GenerateProductVariants`, the same action the panel
 * uses, so the demo data is produced by the production path rather than a
 * seeder-only shortcut — if that action changes shape, this breaks loudly
 * instead of quietly drifting.
 *
 * Each product gets a matrix that exercises the real features: two axes, a
 * colour swatch axis (hex on the option value) next to a text axis, per-variant
 * price/stock/weight, a `list_price` on some rows so the sale badge renders, and
 * one disabled variant so the storefront's enabled filter is visibly doing
 * something.
 *
 * Idempotent: the generator diffs combinations against what exists, so re-running
 * keeps prices, stock and identifiers rather than duplicating the matrix.
 */
class ProductVariantMatrixSeeder extends Seeder
{
    /**
     * Colour axis: label + swatch hex. Kept small so the Cartesian product stays
     * a sane size (3 colours x 4 sizes = 12 SKUs per product).
     *
     * @var list<array{en: string, vi: string, hex: string}>
     */
    protected const COLORS = [
        ['en' => 'Black', 'vi' => 'Đen', 'hex' => '#1a1a1a'],
        ['en' => 'White', 'vi' => 'Trắng', 'hex' => '#f5f5f5'],
        ['en' => 'Navy', 'vi' => 'Xanh navy', 'hex' => '#1f2a44'],
    ];

    /** @var list<array{en: string, vi: string}> */
    protected const SIZES = [
        ['en' => 'S', 'vi' => 'S'],
        ['en' => 'M', 'vi' => 'M'],
        ['en' => 'L', 'vi' => 'L'],
        ['en' => 'XL', 'vi' => 'XL'],
    ];

    /** Per-size weight in grams, so shipping estimates differ across the run. */
    protected const WEIGHT_BY_SIZE = ['S' => 220, 'M' => 240, 'L' => 265, 'XL' => 290];

    public function __construct(protected GeneratesProductVariants $generate) {}

    public function run(): void
    {
        $products = Product::query()
            ->where('status', 'published')
            ->with('variants.values', 'media')
            ->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No published products found — run the catalog seeders first.');

            return;
        }

        $selections = $this->selections();
        $built = 0;

        foreach ($products as $index => $product) {
            // Base price in minor units, taken from whatever price the catalog
            // seeders already set, so the matrix keeps the product's pricing.
            $base = (int) ($product->variants->first()?->prices->first()?->price ?? 29900);

            $this->generate->execute($product, $selections);

            $this->fillVariants($product->refresh(), $base, $index);
            $built++;
        }

        $combinations = count(self::COLORS) * count(self::SIZES);
        $this->command?->info("Built variant matrix for {$built} products ({$combinations} variants each).");
    }

    /**
     * The two axes as `GenerateProductVariants` selections: shared options, by
     * id, with the value ids to combine. Both options and every value are
     * created here if absent, so the seeder works on an empty database.
     *
     * @return array<int, array{type: string, id: int, value_ids: array<int, int>}>
     */
    protected function selections(): array
    {
        $colour = $this->sharedOption('Color', 'Màu sắc', ProductOptionType::Colour);
        $size = $this->sharedOption('Size', 'Kích cỡ', ProductOptionType::Text);

        $colourValues = [];

        foreach (self::COLORS as $position => $definition) {
            $value = $this->optionValue($colour, $definition['en'], $definition['vi'], $position);

            // The swatch hex lives on the value in 2.0 — the storefront picker
            // and the panel both read it from there.
            $meta = (array) ($value->meta ?? []);

            if (($meta['colour'] ?? null) !== strtoupper($definition['hex'])) {
                $meta['colour'] = strtoupper($definition['hex']);
                $value->meta = $meta;
                $value->save();
            }

            $colourValues[] = $value->id;
        }

        $sizeValues = [];

        foreach (self::SIZES as $position => $definition) {
            $sizeValues[] = $this->optionValue($size, $definition['en'], $definition['vi'], $position)->id;
        }

        return [
            ['type' => 'shared', 'id' => $colour->id, 'value_ids' => $colourValues],
            ['type' => 'shared', 'id' => $size->id, 'value_ids' => $sizeValues],
        ];
    }

    protected function sharedOption(string $english, string $vietnamese, ProductOptionType $type): ProductOption
    {
        $option = ProductOption::query()->whereJsonContains('name->en', $english)->first()
            ?? ProductOption::create([
                'name' => ['en' => $english, 'vi' => $vietnamese],
                'label' => ['en' => $english, 'vi' => $vietnamese],
                'handle' => Str::slug($english),
                'shared' => true,
                'type' => $type->value,
            ]);

        if (! $option->shared || $option->type !== $type->value) {
            $option->forceFill(['shared' => true, 'type' => $type->value])->save();
        }

        return $option;
    }

    protected function optionValue(ProductOption $option, string $english, string $vietnamese, int $position): ProductOptionValue
    {
        return $option->values()->whereJsonContains('name->en', $english)->first()
            ?? $option->values()->create([
                'name' => ['en' => $english, 'vi' => $vietnamese],
                'position' => $position + 1,
            ]);
    }

    /**
     * Give each generated variant its demo price, stock, weight and photos.
     *
     * The generator owns which combinations exist; everything a combination
     * carries is set here, matched by the variant's own option values so the
     * pairing never depends on generation order.
     */
    protected function fillVariants(Product $product, int $basePrice, int $productIndex): void
    {
        $prefix = Str::upper(Str::substr(Str::slug($product->translate('name') ?: 'sku'), 0, 6));
        $imagesByColor = $this->imagesByColor($product);
        $currency = Currency::getDefault();
        $location = Location::getDefault();

        foreach ($product->variants()->with('values')->get() as $variant) {
            $names = $variant->values->map(fn (ProductOptionValue $value) => (string) $value->translate('name', 'en'));

            $ci = collect(self::COLORS)->search(fn (array $c) => $names->contains($c['en']));
            $si = collect(self::SIZES)->search(fn (array $s) => $names->contains($s['en']));

            if ($ci === false || $si === false) {
                continue;
            }

            $colour = self::COLORS[$ci];
            $size = self::SIZES[$si];

            // Larger sizes cost a little more, mirroring real fashion pricing.
            $price = $basePrice + ($si * 1000);

            // Put a strike-through list price on the first colour only, so some
            // rows show a sale badge and others do not.
            $listPrice = $ci === 0 ? (int) round($price * 1.25) : null;

            // Vary stock so the demo shows in-stock, low-stock and sold-out
            // states; one XL row per product is deliberately zero.
            $quantity = match (true) {
                $size['en'] === 'XL' && $ci === 2 => 0,
                $size['en'] === 'S' => 4,
                default => 12 + (($productIndex + $ci + $si) % 9),
            };

            $variant->forceFill([
                'sku' => sprintf('%s-%s-%s', $prefix.($productIndex + 1), Str::upper(Str::substr($colour['en'], 0, 3)), $size['en']),
                'model' => $product->translate('name').' / '.$colour['en'].' / '.$size['en'],
                'cost_price' => (int) round($price * 0.55),
                'weight_value' => self::WEIGHT_BY_SIZE[$size['en']] ?? 250,
                'weight_unit' => 'g',
                // Every size of a colour shares that colour's photo set.
                'image_asset_ids' => $imagesByColor[$ci] ?? [],
                // One disabled variant per product, so the storefront's enabled
                // filter is visibly doing something.
                'enabled' => ! ($size['en'] === 'L' && $ci === 2),
            ])->save();

            Price::updateOrCreate(
                [
                    'priceable_type' => $variant->getMorphClass(),
                    'priceable_id' => $variant->id,
                    'currency_id' => $currency->id,
                    'min_quantity' => 1,
                    'customer_group_id' => null,
                ],
                ['price' => $price, 'list_price' => $listPrice],
            );

            $this->setStock($variant, $location, $quantity);
        }
    }

    /**
     * Set on-hand to an absolute figure by recording the difference, so the
     * ledger and the rollup agree — the same path a stock-take takes.
     */
    protected function setStock(ProductVariant $variant, Location $location, int $quantity): void
    {
        $delta = $quantity - (int) $variant->stock_on_hand;

        if ($delta !== 0) {
            app(AdjustsStock::class)->execute($variant, $delta, 'seed');
        }
    }

    /**
     * Split the product's gallery across the colour axis, so each colour owns a
     * distinct photo set and switching colour visibly changes the gallery.
     *
     * The demo products carry ~3 shared photos rather than real per-colour
     * shoots, so this rotates the media list per colour (starting each colour at
     * a different offset) instead of inventing images. Products with a single
     * photo get an empty set, which makes the storefront fall back to the full
     * product gallery — the documented no-own-images behaviour.
     *
     * @return array<int, list<int>> keyed by colour index
     */
    protected function imagesByColor(Product $product): array
    {
        $ids = $product->media->pluck('id')->values();

        if ($ids->count() < 2) {
            return [];
        }

        $byColor = [];

        foreach (array_keys(self::COLORS) as $ci) {
            // Rotate so each colour leads with a different photo but every set
            // stays a real, non-empty subset of the product's own media.
            $byColor[$ci] = $ids
                ->skip($ci % $ids->count())
                ->concat($ids->take($ci % $ids->count()))
                ->values()
                ->all();
        }

        return $byColor;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Enums\StockMovementType;
use Lunar\Core\Models\Location;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\StockLevel;
use Lunar\Core\Models\TaxClass;

/**
 * Hand the shop's purchasable over to Lunar: every `ProductSku` becomes a
 * `ProductVariant`.
 *
 * `ProductSku` was built because Lunar 1.x's variant could not carry per-axis
 * swatches, per-location stock or a real selling policy, and forced variant axes
 * through shared `ProductOption`s when the shop wanted free-form axes per
 * product. Lunar 2.0 has all of that — and the panel's product, variant, stock
 * and order screens are all built on `ProductVariant`, so keeping a private
 * purchasable means the first-party admin shows numbers that are not the shop's.
 *
 * The free-form axes turned out never to be used: all 54 products run the same
 * two axes (Colour, Size), which is exactly what shared options express. See
 * docs/guides/migrate-skus-to-variants.md §0.
 *
 * One-way. Take a backup first — `down()` cannot rebuild what it did not keep.
 *
 * ## What moves, and what deliberately does not
 *
 * Stock arrives as a single `opening_balance` movement carrying the SKU's
 * current quantity, NOT as a replay of the old `stock_movements` ledger. Lunar's
 * ledger holds physical movements only, while the old one conflated them with
 * commitments ("sale" fired at order creation, before anything shipped), so a
 * replay would not reconcile. The old table is left untouched as an archive and
 * the new ledger starts clean, where `sum(movements) == on_hand` holds from the
 * first row.
 *
 * `images` stays a list of Media Library **Asset ids** rather than becoming
 * media owned by the variant: 1,945 references across the catalogue resolve to
 * just 162 distinct assets, so owning them would copy the same files twelve
 * times over and throw away the shared library the Assets module exists for.
 * The column is added to `lunar_product_variants` the same way `model` and
 * `cost_price` were before 2.0 shipped them natively.
 */
return new class extends Migration
{
    /** Written so a later phase — and any incident — can answer "which SKU was this?". */
    private const MAP = 'sku_variant_map';

    private function prefix(): string
    {
        return config('lunar.database.table_prefix', 'lunar_');
    }

    private function skus(): string
    {
        return $this->prefix().'product_skus';
    }

    public function up(): void
    {
        if (! Schema::hasTable($this->skus())) {
            return;
        }

        $this->addVariantColumns();
        $this->createMapTable();

        // Nothing to carry over — a fresh install, where this migration runs
        // against empty tables long before any seeder. Checked BEFORE the
        // location guard below: demanding a default Location on an empty
        // database would fail every fresh install and every test run, for a
        // migration with no work to do.
        if (! DB::table($this->skus())->exists()) {
            return;
        }

        $location = Location::getDefault();

        if (! $location) {
            throw new RuntimeException(
                'No default Location. Run BaseDataSeeder (or create one) before migrating stock.'
            );
        }

        Product::query()->with('skus')->chunkById(50, function ($products) use ($location) {
            foreach ($products as $product) {
                $this->migrateProduct($product, $location);
            }
        });

        $this->repointReferences();
    }

    /**
     * `images` is the shop's own concept (asset ids picked from the shared
     * library); the rest 2.0 already provides.
     */
    /**
     * Which name the image-id column carries on this database.
     *
     * An upgrade that ran this migration before the rename still has `images`
     * at this point; a fresh install has `image_asset_ids`. Writing to whichever
     * exists keeps one migration correct on both.
     */
    private function imagesColumn(): string
    {
        return Schema::hasColumn($this->prefix().'product_variants', 'image_asset_ids')
            ? 'image_asset_ids'
            : 'images';
    }

    private function addVariantColumns(): void
    {
        $variants = $this->prefix().'product_variants';

        // `image_asset_ids`, not `images`: the latter shadows Lunar's own
        // ProductVariant::images() relation, and a real column always beats a
        // relation of the same name. See the rename migration for the crash
        // that taught us.
        if (! Schema::hasColumn($variants, 'image_asset_ids') && ! Schema::hasColumn($variants, 'images')) {
            Schema::table($variants, function (Blueprint $table) {
                $table->json('image_asset_ids')->nullable();
            });
        }

        // Schema, so it runs whether or not there is data to carry over. The
        // shop's own tables key their rows to the purchasable, and on a fresh
        // install there is nothing to migrate but the column is still needed.
        foreach (['stock_notifications', 'stock_movements'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'product_variant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('product_variant_id')->nullable()->index();
            });
        }
    }

    private function createMapTable(): void
    {
        if (Schema::hasTable(self::MAP)) {
            return;
        }

        Schema::create(self::MAP, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_sku_id')->unique();
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->string('sku')->nullable();
        });
    }

    private function migrateProduct(Product $product, Location $location): void
    {
        $axes = $this->axesFor($product);

        foreach ($product->skus as $sku) {
            if (DB::table(self::MAP)->where('product_sku_id', $sku->id)->exists()) {
                continue; // already migrated — safe to re-run
            }

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                // NOT NULL on variants; SKUs allowed null and most rows use it,
                // meaning "the store default" — so say that explicitly.
                'tax_class_id' => $sku->tax_class_id ?? TaxClass::getDefault()?->id,
                'sku' => $sku->sku,
                'model' => $sku->model,
                'cost_price' => $sku->cost_price,
                'weight_value' => $sku->weight,
                'weight_unit' => 'kg',
                'unit_quantity' => 1,
                'shippable' => true,
                'selling_policy' => 'in_stock',
                // Soft-deleted SKUs come across disabled: 2.0 has no soft delete
                // on variants, and historical order lines must still resolve.
                'enabled' => $sku->status === 'published' && $sku->deleted_at === null,
                // Encoded by hand: the column is added by this migration, so the
                // model carries no cast for it yet (that arrives with
                // `ProductVariant::addCasts()` in CatalogServiceProvider).
                $this->imagesColumn() => filled($sku->images) ? json_encode($sku->images) : null,
            ]);

            $this->linkOptionValues($variant, $axes, (array) $sku->variants);
            $this->seedStock($variant, $location, (int) $sku->quantity);

            DB::table(self::MAP)->insert([
                'product_sku_id' => $sku->id,
                'product_variant_id' => $variant->id,
                'sku' => $sku->sku,
            ]);
        }
    }

    /**
     * The product's free-form axes, resolved to shared options and values.
     *
     * Matched on the English name, created when absent — so a catalogue with
     * axes the shared set does not know still comes across whole, and the swatch
     * colour lands on the value where 2.0 expects it (`meta.colour`).
     *
     * @return array<int, array{option: ProductOption, values: array<int, ProductOptionValue>}>
     */
    private function axesFor(Product $product): array
    {
        $axes = [];

        foreach ((array) $product->variables as $axisIndex => $axis) {
            $name = $axis['name'] ?? [];
            $english = $name['en'] ?? ('Option '.($axisIndex + 1));

            $option = ProductOption::query()
                ->whereJsonContains('name->en', $english)
                ->first()
                ?? ProductOption::create([
                    'name' => $name ?: ['en' => $english],
                    'label' => $name ?: ['en' => $english],
                    'handle' => Str::slug($english),
                    'shared' => true,
                    'type' => $this->optionType($axis['display_type'] ?? 'text'),
                ]);

            $values = [];

            foreach ((array) ($axis['values'] ?? []) as $valueIndex => $value) {
                $valueName = $value['name'] ?? [];
                $valueEnglish = $valueName['en'] ?? (string) $valueIndex;

                $optionValue = $option->values()
                    ->whereJsonContains('name->en', $valueEnglish)
                    ->first()
                    ?? $option->values()->create([
                        'name' => $valueName ?: ['en' => $valueEnglish],
                        'position' => $valueIndex + 1,
                    ]);

                if (! empty($value['color'])) {
                    $meta = (array) ($optionValue->meta ?? []);
                    $meta['colour'] = strtoupper($value['color']);
                    $optionValue->meta = $meta;
                    $optionValue->save();
                }

                $values[$valueIndex] = $optionValue;
            }

            // The panel reads a product's axes from this pivot.
            $product->productOptions()->syncWithoutDetaching([
                $option->id => ['position' => $axisIndex + 1],
            ]);

            $axes[$axisIndex] = ['option' => $option, 'values' => $values];
        }

        return $axes;
    }

    private function optionType(string $displayType): string
    {
        return match ($displayType) {
            'color' => ProductOptionType::Colour->value,
            'image' => ProductOptionType::Swatch->value,
            default => ProductOptionType::Text->value,
        };
    }

    /**
     * The SKU's `variants` is a list of value INDEXES into the product's axes —
     * `[0, 2]` meaning "first colour, third size". That positional encoding is
     * what the shared pivot replaces.
     *
     * @param  array<int, array{option: ProductOption, values: array<int, ProductOptionValue>}>  $axes
     * @param  array<int, int>  $combination
     */
    private function linkOptionValues(ProductVariant $variant, array $axes, array $combination): void
    {
        $valueIds = [];

        foreach ($combination as $axisIndex => $valueIndex) {
            $value = $axes[$axisIndex]['values'][$valueIndex] ?? null;

            if ($value) {
                $valueIds[] = $value->id;
            }
        }

        if ($valueIds) {
            $variant->values()->syncWithoutDetaching($valueIds);
        }
    }

    /**
     * One opening balance at the default location, then refresh the rollup.
     *
     * Written directly rather than through `RecordStockMovement` so the
     * migration does not depend on the action graph being bootable mid-migrate;
     * the result is identical because the rollup is recomputed from the level.
     */
    private function seedStock(ProductVariant $variant, Location $location, int $quantity): void
    {
        StockLevel::create([
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'on_hand' => $quantity,
            'incoming' => 0,
            'committed' => 0,
            'unavailable' => 0,
        ]);

        if ($quantity !== 0) {
            DB::table($this->prefix().'stock_movements')->insert([
                'public_id' => (string) Str::ulid(),
                'product_variant_id' => $variant->id,
                'location_id' => $location->id,
                'quantity' => $quantity,
                'type' => StockMovementType::OpeningBalance->value,
                'note' => 'Carried over from ProductSku #'.$variant->sku,
                'created_at' => now(),
            ]);
        }

        $variant->forceFill([
            'stock_on_hand' => $quantity,
            'stock_available' => $quantity,
        ])->save();
    }

    /**
     * Point everything that referenced a SKU at its variant.
     *
     * Order lines are historical data — an invoice or an RMA whose purchasable
     * no longer resolves is a silent hole — so they move with everything else.
     */
    private function repointReferences(): void
    {
        $map = self::MAP;
        $variantMorph = (new ProductVariant)->getMorphClass();

        $morphs = [
            [$this->prefix().'prices', 'priceable_type', 'priceable_id'],
            [$this->prefix().'cart_lines', 'purchasable_type', 'purchasable_id'],
            [$this->prefix().'order_lines', 'purchasable_type', 'purchasable_id'],
            [$this->prefix().'discountables', 'discountable_type', 'discountable_id'],
        ];

        foreach ($morphs as [$table, $typeColumn, $idColumn]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(
                "update `{$table}` t
                 join `{$map}` m on m.product_sku_id = t.`{$idColumn}`
                 set t.`{$idColumn}` = m.product_variant_id, t.`{$typeColumn}` = ?
                 where t.`{$typeColumn}` = 'product_sku'",
                [$variantMorph],
            );
        }

        // The shop's own tables keyed to a SKU (the columns themselves are added
        // in addVariantColumns(), which runs unconditionally).
        foreach (['stock_notifications', 'stock_movements'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_sku_id')) {
                continue;
            }

            DB::statement(
                "update `{$table}` t join `{$map}` m on m.product_sku_id = t.product_sku_id
                 set t.product_variant_id = m.product_variant_id"
            );
        }
    }

    public function down(): void
    {
        // One-way: the variants, option links and stock levels created here
        // cannot be told apart from ones created since. Restore from the backup
        // taken before running (docs/guides/migrate-skus-to-variants.md §4).
    }
};

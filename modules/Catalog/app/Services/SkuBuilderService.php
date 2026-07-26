<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Modules\Assets\Definitions\FashionMediaDefinitions;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Catalog\Models\ProductSku;

/**
 * Persists a product's flexible variant definition (VaniCommerce model):
 *
 *   variables  — free-form JSON variant definitions on the product.
 *   skus[]     — one row per Cartesian combination, each with its own
 *                price / quantity / images / sku / status.
 *
 * Save strategy is delete-and-recreate (mirrors VaniCommerce ProductService):
 * the posted `skus` array is authoritative, so the stored set is always
 * internally consistent with `variables`. Because ids are therefore not stable
 * across edits, purchasables are identified downstream by the `sku` string, and
 * the admin builder preserves per-SKU data across a rebuild by MATCHING on the
 * `variants` index combination (see saveFromBuilder's carry-over), not the id.
 *
 * The authoritative price the Lunar Pricing engine reads lives in
 * `lunar_prices` (priceable_type = product_sku). This service syncs each SKU's
 * `price` column down to a base Price row so the engine and the admin cache
 * never drift.
 */
class SkuBuilderService
{
    /**
     * Generate every Cartesian combination of the given variables' value
     * indexes. A variable with values [Black, White] and another [S, M] yields
     * [[0,0],[0,1],[1,0],[1,1]] — one entry per resulting SKU.
     *
     * @param  array<int, array{values?: array}>  $variables
     * @return array<int, array<int, int>>
     */
    public function combinations(array $variables): array
    {
        $axes = collect($variables)
            ->map(fn ($variable) => array_keys($variable['values'] ?? []))
            ->reject(fn ($indexes) => empty($indexes))
            ->values()
            ->all();

        if (empty($axes)) {
            return [];
        }

        $result = [[]];

        foreach ($axes as $indexes) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($indexes as $index) {
                    $next[] = [...$prefix, $index];
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * Persist the product's variables + SKU rows.
     *
     * @param  array<int, mixed>  $variables  the variant definitions
     * @param  array<int, array<string, mixed>>  $skus  one row per combination
     *
     * @throws ValidationException when a SKU code collides, or when the SKU
     *                             combinations do not match the variables
     */
    public function save(Product $product, array $variables, array $skus): void
    {
        $variables = array_values($variables);
        $skus = array_values($skus);

        // Re-derive the canonical combinations from the variables and bind them
        // to the SKU rows BY POSITION. The admin's SKU repeater is non-reorderable
        // and non-addable, so its rows stay in Cartesian order; taking `variants`
        // from the fresh combinations (not the posted, possibly-stale values)
        // makes it impossible to persist an index that no longer matches the
        // variables — even a pure axis reorder, which leaves the combo-key SET
        // unchanged but changes each position's meaning. The count still has to
        // line up, else the payload is genuinely out of sync (missing/extra rows).
        $combinations = $this->combinations($variables);
        $this->assertSkuCountMatches($combinations, $skus);
        $this->assertUniqueSkuCodes($product, $skus);
        $this->assertStockCoversCommitments($product, $skus);

        DB::transaction(function () use ($product, $variables, $skus, $combinations) {
            // Move any freshly-uploaded swatch files into the Spatie `swatch`
            // media collection and rewrite the blob to store media ids, so the
            // storefront can serve a small conversion instead of the original.
            $variables = $this->ingestSwatchMedia($product, $variables);

            $product->variables = $variables;
            $product->save();

            // Move any freshly-uploaded per-SKU photos into the product's own
            // gallery collection (the same one product-level images live in),
            // rewriting each row's `images` to the resulting media ids.
            $skus = $this->ingestSkuImages($product, $skus);

            // Snapshot the old SKUs' ids keyed by their durable `sku` code BEFORE
            // dropping them, so anything that referenced a SKU by its (disposable)
            // id can be re-pointed at the recreated row that carries the same code.
            $oldIdsBySku = $product->skus()->pluck('id', 'sku');

            // Committed units are NOT the admin's to edit — they are the units
            // open orders have already claimed. The editor's payload has no such
            // field, so without carrying it across this rewrite every hold would
            // silently vanish and sold stock would go back on sale.
            $committedBySku = $product->skus()->pluck('committed', 'sku');

            // Authoritative rewrite: drop the old set, recreate from the payload.
            $product->skus()->forceDelete();

            $currency = Currency::getDefault();
            $newIdsBySku = [];

            foreach (array_values($skus) as $position => $row) {
                $sku = $product->skus()->create([
                    // Canonical combo for this position (empty for a simple product).
                    'variants' => $combinations[$position] ?? [],
                    'position' => $position,
                    'images' => $row['images'] ?? [],
                    'model' => $row['model'] ?? '',
                    'sku' => $row['sku'],
                    'price' => (int) ($row['price'] ?? 0),
                    'origin_price' => (int) ($row['origin_price'] ?? 0),
                    'cost_price' => isset($row['cost_price']) ? (int) $row['cost_price'] : null,
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    // Carried from the pre-rewrite row, never from the payload.
                    'committed' => (int) ($committedBySku[$row['sku']] ?? 0),
                    'weight' => $row['weight'] ?? null,
                    'tax_class_id' => $row['tax_class_id'] ?? null,
                    'is_default' => (bool) ($row['is_default'] ?? $position === 0),
                    'status' => $row['status'] ?? 'published',
                ]);

                $newIdsBySku[$row['sku']] = $sku->id;
                $this->syncBasePrice($sku, $currency);
            }

            // Re-point every id-based reference (discount targets, cart lines,
            // order lines) from the deleted SKU to the recreated one that shares
            // the same `sku` code. Without this, delete-and-recreate silently
            // breaks variant-scoped discounts on every save and orphans cart/order
            // lines against a since-deleted id.
            $this->repointBySkuCode($oldIdsBySku->all(), $newIdsBySku);

            // Guarantee exactly one default, and prefer a PUBLISHED one — the
            // default SKU sets the product's headline price (PricingService::
            // displayPrice), so a disabled default would surface a hidden price.
            // Fall back to the first SKU only when nothing is published (all
            // disabled), so the invariant "≥1 default" always holds.
            $hasPublishedDefault = $product->skus()
                ->where('is_default', true)->where('status', 'published')->exists();

            if (! $hasPublishedDefault) {
                $product->skus()->update(['is_default' => false]);

                $promote = $product->skus()->where('status', 'published')->orderBy('position')->first()
                    ?? $product->skus()->orderBy('position')->first();

                $promote?->update(['is_default' => true]);
            }
        });
    }

    /**
     * Mirror a SKU's `price` cache down to its authoritative base Price row
     * (min_quantity 1, default currency, no customer group). `compare_price`
     * carries the strike-through `origin_price` when it is higher.
     *
     * A non-positive price means "not priced yet" — we DELETE the base row
     * rather than write a 0 one. Lunar's pricing engine treats a 0 row as a
     * valid match, so a blank/zero price would otherwise let the storefront sell
     * the variant for free; with no row, the engine reports the SKU as unpriced
     * and the storefront hides it / refuses add-to-cart.
     */
    protected function syncBasePrice(ProductSku $sku, ?Currency $currency): void
    {
        if (! $currency) {
            return;
        }

        $baseKey = [
            'currency_id' => $currency->id,
            'customer_group_id' => null,
            'min_quantity' => 1,
        ];

        if ((int) $sku->price <= 0) {
            $sku->prices()->where($baseKey)->delete();

            return;
        }

        $sku->prices()->updateOrCreate(
            $baseKey,
            [
                'price' => (int) $sku->price,
                'compare_price' => $sku->origin_price > $sku->price ? (int) $sku->origin_price : null,
            ],
        );
    }

    /**
     * Re-point id-based references from a product's deleted SKUs to the freshly
     * recreated ones, matched by the durable `sku` code.
     *
     * `save()` delete-and-recreates SKUs (ids change), but discount targets and
     * cart/order lines store the SKU id. For every code that survived the save,
     * move those rows from the old id to the new id so variant-scoped discounts
     * keep applying and existing cart/order lines still resolve. The morph type
     * (`product_sku`) is unchanged, so only the id needs updating.
     *
     * @param  array<string, int>  $oldIdsBySku  sku code => id before the save
     * @param  array<string, int>  $newIdsBySku  sku code => id after the save
     */
    protected function repointBySkuCode(array $oldIdsBySku, array $newIdsBySku): void
    {
        $morph = (new ProductSku)->getMorphClass();

        foreach ($oldIdsBySku as $code => $oldId) {
            $newId = $newIdsBySku[$code] ?? null;

            // Code gone (variant genuinely removed) or unchanged id → nothing to move.
            if ($newId === null || (int) $newId === (int) $oldId) {
                continue;
            }

            DB::table('lunar_discountables')
                ->where('discountable_type', $morph)->where('discountable_id', $oldId)
                ->update(['discountable_id' => $newId]);

            foreach (['lunar_cart_lines', 'lunar_order_lines'] as $table) {
                DB::table($table)
                    ->where('purchasable_type', $morph)->where('purchasable_id', $oldId)
                    ->update(['purchasable_id' => $newId]);
            }
        }
    }

    /**
     * The number of SKU rows must equal the number of Cartesian combinations of
     * the variables. Since `save()` rebinds each SKU's `variants` from the
     * canonical combinations by position, a count mismatch is the tell-tale that
     * the admin edited the variable definitions without regenerating the SKU
     * list — reject it rather than persist rows whose commercial data lines up
     * with the wrong combination.
     *
     * A simple product (no variables) legitimately has one SKU with an empty
     * combination; combinations() returns [] for no axes, so allow a single row.
     *
     * @param  array<int, array<int, int>>  $combinations
     * @param  array<int, array<string, mixed>>  $skus
     *
     * @throws ValidationException
     */
    protected function assertSkuCountMatches(array $combinations, array $skus): void
    {
        $expected = count($combinations) ?: 1;

        if (count($skus) !== $expected) {
            throw ValidationException::withMessages([
                'skus' => 'The variants were changed but the SKU list is out of date. '
                    .'Click "Generate combinations" to rebuild the SKUs before saving.',
            ]);
        }
    }

    /**
     * A SKU code must be unique across the whole catalogue. Reject a payload
     * that reuses a code owned by a DIFFERENT product (VaniCommerce guard).
     *
     * @param  array<int, array<string, mixed>>  $skus
     *
     * @throws ValidationException
     */
    /**
     * Refuse a stock figure lower than what open orders have already claimed.
     *
     * The editor writes an absolute quantity, so typing 1 into a SKU with 3
     * units committed would leave the shop having sold goods it cannot ship —
     * and nothing downstream would notice, because sellable clamps at 0 either
     * way. The honest fix is to cancel the orders that cannot be filled, so say
     * that rather than silently accepting an impossible number.
     *
     * @param  array<int, array<string, mixed>>  $skus
     *
     * @throws ValidationException
     */
    protected function assertStockCoversCommitments(Product $product, array $skus): void
    {
        $committedBySku = $product->skus()->pluck('committed', 'sku');

        $short = collect($skus)
            ->filter(function (array $row) use ($committedBySku): bool {
                $committed = (int) ($committedBySku[$row['sku'] ?? ''] ?? 0);

                return $committed > 0 && (int) ($row['quantity'] ?? 0) < $committed;
            })
            ->map(fn (array $row) => sprintf(
                '%s (%d < %d)',
                $row['sku'],
                (int) ($row['quantity'] ?? 0),
                (int) $committedBySku[$row['sku']],
            ));

        if ($short->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skus' => 'Stock cannot be set below the units already sold and awaiting dispatch: '
                    .$short->implode(', ').'. Cancel those orders first if the goods really are gone.',
            ]);
        }
    }

    protected function assertUniqueSkuCodes(Product $product, array $skus): void
    {
        $codes = collect($skus)->pluck('sku')->filter();

        // Duplicate within the same payload.
        $dupInPayload = $codes->duplicates();
        if ($dupInPayload->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skus' => 'Duplicate SKU code in this product: '.$dupInPayload->implode(', '),
            ]);
        }

        $clashing = ProductSku::query()
            ->withTrashed()
            ->whereIn('sku', $codes->all())
            ->where('product_id', '!=', $product->id)
            ->pluck('sku');

        if ($clashing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skus' => 'These SKU codes are already used by another product: '.$clashing->unique()->implode(', '),
            ]);
        }
    }

    /**
     * Carry per-SKU commercial data (price, quantity, images, status, code)
     * forward when the admin rebuilds the matrix, so adding/removing an option
     * value does NOT wipe the SKUs that still exist — matched by their
     * `variants` index combination, which is stable while ids are not.
     *
     * @param  array<int, array<int, int>>  $combinations  fresh index combos
     * @return Collection<int, array<string, mixed>> builder rows
     */
    public function carryOver(Product $product, array $combinations): Collection
    {
        $existing = $product->skus()
            ->get()
            ->keyBy(fn (ProductSku $sku) => $this->comboKey($sku->variants ?? []));

        return collect($combinations)->map(function (array $combo, int $i) use ($existing) {
            $prior = $existing->get($this->comboKey($combo));

            return [
                'variants' => $combo,
                'position' => $i,
                'sku' => $prior->sku ?? '',
                'model' => $prior->model ?? '',
                'price' => $prior->price ?? 0,
                'origin_price' => $prior->origin_price ?? 0,
                'cost_price' => $prior->cost_price ?? null,
                'quantity' => $prior->quantity ?? 0,
                'weight' => $prior->weight ?? null,
                'images' => $prior->images ?? [],
                'is_default' => $prior->is_default ?? ($i === 0),
                'status' => $prior->status ?? 'published',
            ];
        });
    }

    /**
     * Stable key for a combination of value indexes, e.g. [0,1] => "0-1".
     *
     * @param  array<int, int>  $combo
     */
    protected function comboKey(array $combo): string
    {
        return implode('-', $combo);
    }

    /**
     * Normalise every image-swatch value to a Spatie media id: keep existing
     * ids, ingest freshly-uploaded temp files into the `swatch` collection, and
     * delete media no longer referenced. Returns the rewritten variables.
     *
     * @param  array<int, mixed>  $variables
     * @return array<int, mixed>
     */
    protected function ingestSwatchMedia(Product $product, array $variables): array
    {
        $collection = FashionMediaDefinitions::SWATCH_COLLECTION;

        // Existing swatch media, indexed by their on-disk path (to recognise a
        // hydrated id that came back unchanged from the form).
        $existing = $product->getMedia($collection);
        $pathToId = $existing->mapWithKeys(fn ($m) => [$m->getPathRelativeToRoot() => $m->id])->all();

        $keptIds = [];

        foreach ($variables as $ai => $axis) {
            if (($axis['display_type'] ?? null) !== 'image') {
                continue;
            }

            foreach ($axis['values'] ?? [] as $vi => $value) {
                $img = $value['image'] ?? null;
                $variables[$ai]['values'][$vi]['image'] = $this->ingestSwatchValue(
                    $product, $collection, $img, $pathToId, $keptIds
                );
            }
        }

        // Orphans: swatch media no longer referenced by any value.
        $existing->reject(fn ($m) => in_array($m->id, $keptIds, true))
            ->each(fn ($m) => $m->delete());

        return $variables;
    }

    /**
     * Resolve one swatch value to a media id (or null). Mutates $keptIds.
     *
     * @param  array<string, int>  $pathToId
     * @param  array<int, int>  $keptIds
     */
    protected function ingestSwatchValue(Product $product, string $collection, $img, array $pathToId, array &$keptIds): ?int
    {
        if (empty($img)) {
            return null;
        }

        // Already a media id → keep as-is.
        if (is_numeric($img)) {
            $keptIds[] = (int) $img;

            return (int) $img;
        }

        // A hydrated path pointing at an existing swatch media → map back to id.
        if (isset($pathToId[$img])) {
            $keptIds[] = $pathToId[$img];

            return $pathToId[$img];
        }

        // A freshly uploaded temp file on the media disk → move it into the
        // collection via the shared ingest (no preservingOriginal → temp file
        // is cleaned up). Missing file → null.
        $media = app(MediaLibraryService::class)->ingestFromDisk($product, $img, $collection);

        if ($media) {
            $keptIds[] = $media->id;

            return $media->id;
        }

        return null;
    }

    /**
     * Turn stored swatch media ids back into disk paths so the admin FileUpload
     * can preview the saved image. Called from the page's fill. Ids with no
     * surviving media become null. Non-id values (legacy paths/URLs) pass through.
     *
     * @param  array<int, mixed>  $variables
     * @return array<int, mixed>
     */
    public function hydrateSwatchPaths(Product $product, array $variables): array
    {
        $byId = $product->getMedia(FashionMediaDefinitions::SWATCH_COLLECTION)->keyBy('id');

        foreach ($variables as $ai => $axis) {
            if (($axis['display_type'] ?? null) !== 'image') {
                continue;
            }

            foreach ($axis['values'] ?? [] as $vi => $value) {
                $img = $value['image'] ?? null;

                if (is_numeric($img)) {
                    $variables[$ai]['values'][$vi]['image'] = $byId->get((int) $img)?->getPathRelativeToRoot();
                }
            }
        }

        return $variables;
    }

    /**
     * Normalise each SKU row's `images` to a list of Spatie media ids: keep
     * existing ids, ingest freshly-uploaded temp files into the product's own
     * gallery collection (the same collection the product-level photos live
     * in, so a SKU can point at either its own shots or the shared gallery).
     *
     * Unlike swatch media, a SKU photo is never deleted here just because a
     * row no longer references it — it may still be the product's gallery
     * image or reused by another SKU, so orphan cleanup is not this method's
     * call to make.
     *
     * @param  array<int, array<string, mixed>>  $skus
     * @return array<int, array<string, mixed>>
     */
    protected function ingestSkuImages(Product $product, array $skus): array
    {
        $collection = config('lunar.media.collection', 'images');

        // Existing gallery media, indexed by on-disk path, to recognise a
        // hydrated path that came back unchanged from the form.
        $pathToId = $product->getMedia($collection)
            ->mapWithKeys(fn ($m) => [$m->getPathRelativeToRoot() => $m->id])->all();

        foreach ($skus as $i => $row) {
            $images = collect($row['images'] ?? [])
                ->map(fn ($img) => $this->ingestSkuImageValue($product, $collection, $img, $pathToId))
                ->filter()
                ->values()
                ->all();

            $skus[$i]['images'] = $images;
        }

        return $skus;
    }

    /**
     * Resolve one SKU image value (a media id, a hydrated path, or a freshly
     * uploaded temp path) to a media id, or null when it cannot be resolved.
     *
     * @param  array<string, int>  $pathToId
     */
    protected function ingestSkuImageValue(Product $product, string $collection, mixed $img, array $pathToId): ?int
    {
        if (empty($img)) {
            return null;
        }

        // Already a media id → keep as-is.
        if (is_numeric($img)) {
            return (int) $img;
        }

        // A hydrated path pointing at an existing gallery media → map back to id.
        if (is_string($img) && isset($pathToId[$img])) {
            return $pathToId[$img];
        }

        // A freshly uploaded temp file on the media disk → move it into the
        // product's gallery collection.
        if (! is_string($img)) {
            return null;
        }

        return app(MediaLibraryService::class)->ingestFromDisk($product, $img, $collection)?->id;
    }

    /**
     * Turn a SKU's stored image media ids back into disk paths so the admin
     * FileUpload can preview the saved images. Ids with no surviving media
     * are dropped.
     *
     * @param  array<int, mixed>  $images
     * @return array<int, string>
     */
    public function hydrateSkuImagePaths(Product $product, array $images): array
    {
        $collection = config('lunar.media.collection', 'images');
        $byId = $product->getMedia($collection)->keyBy('id');

        return collect($images)
            ->map(fn ($id) => is_numeric($id) ? $byId->get((int) $id)?->getPathRelativeToRoot() : null)
            ->filter()
            ->values()
            ->all();
    }
}

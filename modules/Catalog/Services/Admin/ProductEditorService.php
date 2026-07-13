<?php

namespace Modules\Catalog\Services\Admin;

use Illuminate\Support\Str;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\Url;

/**
 * Fill/persist glue for the single-page product editor (ProductEditor page).
 *
 * The editor's form mixes real product columns (status, brand_id, ...,
 * attribute_data — saved by Filament) with data living on other aggregates:
 * first-variant SKU/price/stock, the default URL slug, collection links and
 * cross-sell associations. This service owns that mapping so the page stays a
 * thin Filament layer and the logic is reusable (API/import jobs).
 */
class ProductEditorService
{
    public const RELATED_TYPE = 'cross-sell';

    /**
     * Extra form state for fields that aren't product columns.
     *
     * @return array<string, mixed>
     */
    public function fill(Product $product): array
    {
        $variant = $product->variants->first();
        $basePrice = $variant?->basePrices->first();

        $defaultUrls = $product->urls()->where('default', true)->get()->keyBy('language_id');

        return [
            'sku' => $variant?->sku,
            'price' => $basePrice?->price->decimal,
            'stock' => $variant?->stock,
            'slugs' => Language::all()->mapWithKeys(
                fn (Language $language) => [$language->code => $defaultUrls->get($language->id)?->slug]
            )->all(),
            'collection_ids' => $product->collections()->pluck('lunar_collections.id')->all(),
            'related_ids' => $product->associations()
                ->where('type', self::RELATED_TYPE)
                ->pluck('product_target_id')
                ->all(),
        ];
    }

    /**
     * Persist the non-column editor state. Runs inside the page's save.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Product $product, array $data): void
    {
        $this->saveSimpleVariant($product, $data);
        $this->saveSlugs($product, $data['slugs'] ?? []);
        $this->saveCollections($product, $data['collection_ids'] ?? []);
        $this->saveRelated($product, $data['related_ids'] ?? []);
    }

    /**
     * SKU/price/stock shown on the page only for option-less products (a
     * single default variant). Products with real variants manage these in
     * the variants matrix instead, so the keys are absent from $data.
     */
    protected function saveSimpleVariant(Product $product, array $data): void
    {
        if (! array_key_exists('sku', $data)) {
            return;
        }

        $variant = $product->variants->first();

        if (! $variant) {
            return;
        }

        $variant->sku = $data['sku'];
        $variant->stock = (int) ($data['stock'] ?? 0);
        $variant->save();

        $currency = Currency::getDefault();
        $basePrice = $variant->basePrices->first()
            ?? $variant->prices()->make([
                'min_quantity' => 1,
                'currency_id' => $currency->id,
            ]);

        $basePrice->price = (int) bcmul(
            (string) ((float) ($data['price'] ?? 0)),
            (string) $basePrice->currency->factor
        );
        $basePrice->save();
    }

    /**
     * Upsert one default URL per language (Lunar's multilingual URL model).
     * Empty inputs are skipped; a slug already used by another element in the
     * same language gets a -{id} suffix instead of failing the whole save.
     *
     * @param  array<string, ?string>  $slugs  keyed by language code
     */
    protected function saveSlugs(Product $product, array $slugs): void
    {
        foreach (Language::all() as $language) {
            $slug = Str::slug((string) ($slugs[$language->code] ?? ''));

            if ($slug === '') {
                continue;
            }

            $url = $product->urls()
                ->where('language_id', $language->id)
                ->where('default', true)
                ->first();

            if ($url && $url->slug === $slug) {
                continue;
            }

            $taken = Url::where('slug', $slug)
                ->where('language_id', $language->id)
                ->where(fn ($query) => $query
                    ->where('element_type', '!=', $product->getMorphClass())
                    ->orWhere('element_id', '!=', $product->id))
                ->exists();

            if ($taken) {
                $slug = "{$slug}-{$product->id}";
            }

            if ($url) {
                $url->update(['slug' => $slug]);

                continue;
            }

            $product->urls()->create([
                'slug' => $slug,
                'default' => true,
                'language_id' => $language->id,
            ]);
        }
    }

    /** @param  list<int|string>  $ids */
    protected function saveCollections(Product $product, array $ids): void
    {
        $product->collections()->sync(
            collect($ids)->values()->mapWithKeys(
                fn ($id, $index) => [(int) $id => ['position' => $index + 1]]
            )->all()
        );
    }

    /**
     * Sync cross-sell associations ("related products") without touching
     * other association types (up-sell/alternate stay as data even though the
     * editor doesn't surface them).
     *
     * @param  list<int|string>  $ids
     */
    protected function saveRelated(Product $product, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();

        $product->associations()
            ->where('type', self::RELATED_TYPE)
            ->whereNotIn('product_target_id', $ids)
            ->delete();

        $existing = $product->associations()
            ->where('type', self::RELATED_TYPE)
            ->pluck('product_target_id');

        foreach ($ids->diff($existing) as $targetId) {
            $product->associations()->create([
                'product_target_id' => $targetId,
                'type' => self::RELATED_TYPE,
            ]);
        }
    }

    /**
     * After the gallery upload saves, mark the first image as primary so
     * Lunar's thumbnail relation (custom_properties->primary) keeps working
     * with drag-drop ordering.
     */
    public function normalizeGallery(Product $product): void
    {
        $media = $product->refresh()->getMedia(config('lunar.media.collection', 'images'));

        foreach ($media->values() as $index => $item) {
            $primary = $index === 0;

            if ((bool) $item->getCustomProperty('primary') !== $primary) {
                $item->setCustomProperty('primary', $primary);
                $item->save();
            }
        }
    }
}

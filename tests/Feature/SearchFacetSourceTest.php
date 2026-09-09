<?php

namespace Tests\Feature;

use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Where the sidebar's size/colour counts come from.
 *
 * They used to be decoded from `products.variables`, a JSON blob this shop
 * maintained itself, while the *filter*已 queried Lunar's product options. Two
 * sources of truth, and since the panel took over variant editing it only ever
 * writes the option tables — so the blob went stale the first time anyone
 * touched a product in the admin.
 *
 * What these pin is that the facet and the filter now read the same thing: a
 * value the sidebar offers must return results, and one it hides must not
 * exist.
 */
class SearchFacetSourceTest extends TestCase
{
    use CreatesStorefrontData;

    private function facets(array $filters = []): array
    {
        return app(SearchEngine::class)
            ->search(new SearchQuery(filters: $filters))
            ->facets;
    }

    private function values(array $facets, string $key): array
    {
        return collect($facets[$key] ?? [])->pluck('count', 'value')->sortKeys()->all();
    }

    public function test_option_facets_are_built_from_lunars_option_tables(): void
    {
        $this->createProduct(['options' => ['Color' => 'Black', 'Size' => 'M']]);
        $this->createProduct(['options' => ['Color' => 'Black', 'Size' => 'L']]);

        $facets = $this->facets();

        // Two products share Black; each size belongs to one.
        $this->assertSame(['Black' => 2], $this->values($facets, 'color'));
        $this->assertSame(['L' => 1, 'M' => 1], $this->values($facets, 'size'));
    }

    /**
     * The regression that made this worth changing: editing options through the
     * panel updates the option tables and nothing else. A facet reading a blob
     * would keep showing the old answer forever.
     */
    public function test_a_panel_side_option_change_moves_the_facet(): void
    {
        $product = $this->createProduct(['options' => ['Color' => 'Black']]);

        $this->assertSame(['Black' => 1], $this->values($this->facets(), 'color'));

        // Exactly what the panel does: repoint the variant at a different
        // option value. It never touches `products.variables`.
        $navy = $this->optionValue($this->createProduct(['options' => ['Color' => 'Navy']]), 'Color', 'Navy');

        $product->variants->first()->values()->sync([$navy->id]);

        $this->assertSame(['Navy' => 2], $this->values($this->facets(), 'color'));
    }

    /** Counted per product, not per variant: one shelf item, one count. */
    public function test_a_value_on_several_variants_counts_its_product_once(): void
    {
        $product = $this->createProduct(['options' => ['Color' => 'Black', 'Size' => 'M']]);
        $black = $this->optionValue($product, 'Color', 'Black');

        $second = $product->variants()->create([
            'sku' => $product->variants->first()->sku.'-2',
            'tax_class_id' => $product->variants->first()->tax_class_id,
            'enabled' => true,
        ]);
        $second->values()->sync([$black->id]);

        $this->assertSame(['Black' => 1], $this->values($this->facets(), 'color'));
    }

    /**
     * Facet and filter must agree: every value the sidebar offers has to return
     * at least one product when clicked.
     */
    public function test_every_offered_facet_value_returns_results(): void
    {
        $this->createProduct(['options' => ['Color' => 'Black', 'Size' => 'S']]);
        $this->createProduct(['options' => ['Color' => 'Navy', 'Size' => 'M']]);

        $facets = $this->facets();

        foreach (['color', 'size'] as $key) {
            foreach ($facets[$key] as $entry) {
                $result = app(SearchEngine::class)
                    ->search(new SearchQuery(filters: [$key => [$entry['value']]]));

                $this->assertSame(
                    $entry['count'],
                    $result->total,
                    "Facet [{$key}={$entry['value']}] hứa {$entry['count']} nhưng lọc ra {$result->total}.",
                );
            }
        }
    }

    /**
     * The blob is gone from the schema, not merely unread — which is the only
     * version of "retired" that cannot quietly come back.
     */
    public function test_the_legacy_variables_column_no_longer_exists(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn(
                config('lunar.database.table_prefix').'products',
                'variables',
            ),
            'products.variables đã quay lại — facet lại có hai nguồn sự thật.',
        );
    }
}

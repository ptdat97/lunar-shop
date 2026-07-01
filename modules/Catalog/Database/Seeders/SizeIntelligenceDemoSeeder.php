<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\SizeChart;

/**
 * Demo data for Fashion Size Intelligence: a few reusable size charts, assigned
 * to products (one chart per product), plus per-product material/care.
 *
 * Idempotent: charts keyed by name, assignment via sync, material upserted.
 */
class SizeIntelligenceDemoSeeder extends Seeder
{
    /** Reusable charts: name => [category, rows]. */
    protected function charts(): array
    {
        return [
            "Women's Tops" => ['tops', [
                ['size' => 'S',  'fit' => 'regular', 'bust' => '84-88',  'waist' => '64-68', 'hip' => '90-94'],
                ['size' => 'M',  'fit' => 'regular', 'bust' => '88-92',  'waist' => '68-72', 'hip' => '94-98'],
                ['size' => 'L',  'fit' => 'regular', 'bust' => '92-97',  'waist' => '72-77', 'hip' => '98-103'],
                ['size' => 'XL', 'fit' => 'relaxed', 'bust' => '97-103', 'waist' => '77-83', 'hip' => '103-109'],
            ]],
            "Men's Tops" => ['tops', [
                ['size' => 'S',  'fit' => 'regular', 'bust' => '92-96',   'waist' => '78-82', 'shoulder' => '43'],
                ['size' => 'M',  'fit' => 'regular', 'bust' => '96-100',  'waist' => '82-86', 'shoulder' => '45'],
                ['size' => 'L',  'fit' => 'regular', 'bust' => '100-105', 'waist' => '86-91', 'shoulder' => '47'],
                ['size' => 'XL', 'fit' => 'relaxed', 'bust' => '105-110', 'waist' => '91-97', 'shoulder' => '49'],
            ]],
            "Women's Bottoms" => ['bottoms', [
                ['size' => 'S',  'fit' => 'slim',    'waist' => '64-68', 'hip' => '90-94',   'inseam' => '76'],
                ['size' => 'M',  'fit' => 'regular', 'waist' => '68-72', 'hip' => '94-98',   'inseam' => '78'],
                ['size' => 'L',  'fit' => 'regular', 'waist' => '72-77', 'hip' => '98-103',  'inseam' => '80'],
                ['size' => 'XL', 'fit' => 'relaxed', 'waist' => '77-83', 'hip' => '103-109', 'inseam' => '82'],
            ]],
        ];
    }

    protected function materials(): array
    {
        return [
            ['material' => 'Cotton', 'composition' => '100% Organic Cotton', 'fabric_weight' => '180 gsm', 'stretch' => 'slight', 'transparency' => 'opaque', 'lining' => 'none', 'care_instruction' => 'Machine wash cold, tumble dry low, warm iron.'],
            ['material' => 'Linen', 'composition' => '55% Linen, 45% Cotton', 'fabric_weight' => '160 gsm', 'stretch' => 'none', 'transparency' => 'semi', 'lining' => 'none', 'care_instruction' => 'Hand wash or gentle cycle, line dry, iron while damp.'],
            ['material' => 'Wool blend', 'composition' => '70% Wool, 30% Polyamide', 'fabric_weight' => '320 gsm', 'stretch' => 'slight', 'transparency' => 'opaque', 'lining' => 'full', 'care_instruction' => 'Dry clean only.'],
            ['material' => 'Denim', 'composition' => '98% Cotton, 2% Elastane', 'fabric_weight' => '340 gsm', 'stretch' => 'stretchy', 'transparency' => 'opaque', 'lining' => 'none', 'care_instruction' => 'Wash inside out cold, hang dry.'],
        ];
    }

    public function run(): void
    {
        $charts = $this->seedCharts();
        $this->assignToProducts($charts);
    }

    /**
     * Create the reusable charts + rows.
     *
     * @return array<string, SizeChart>  name => chart
     */
    protected function seedCharts(): array
    {
        $out = [];

        foreach ($this->charts() as $name => [$category, $rows]) {
            $chart = SizeChart::firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'active' => true],
            );

            // Rebuild rows cleanly so re-seeding doesn't duplicate.
            $chart->rows()->delete();

            foreach ($rows as $sort => $row) {
                $chart->rows()->create([...$row, 'sort' => $sort]);
            }

            $out[$name] = $chart;
        }

        return $out;
    }

    /**
     * Assign a chart to every product (by gender via collections) + material.
     *
     * @param  array<string, SizeChart>  $charts
     */
    protected function assignToProducts(array $charts): void
    {
        $materials = $this->materials();

        $products = Product::query()
            ->with('collections.urls')
            ->orderBy('id')
            ->get();

        foreach ($products as $i => $product) {
            $isMen = $product->collections->contains(
                fn ($c) => $c->urls->contains(fn ($u) => $u->slug === 'men'),
            );

            $chart = $isMen ? $charts["Men's Tops"] : $charts["Women's Tops"];

            $product->sizeChart()->sync([$chart->id]);

            ProductMaterial::updateOrCreate(
                ['product_id' => $product->id],
                $materials[$i % count($materials)],
            );
        }
    }
}

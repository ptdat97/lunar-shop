<?php

namespace Modules\Product\Services;

use Lunar\Models\Product;
use Modules\Product\Models\ProductMaterial;
use Modules\Product\Models\SizeChart;
use Modules\Product\Models\SizeChartRow;

/**
 * Assembles the size chart + material payload for a product from its assigned
 * reusable SizeChart. Single source used by both the storefront controller and
 * the API (no duplicated logic).
 */
class SizeChartService
{
    /**
     * The product's assigned size chart (or null).
     */
    public function chartFor(Product $product): ?SizeChart
    {
        return $product->sizeChart()->with('rows')->first();
    }

    /**
     * Size chart rows + product material for the storefront / API.
     *
     * @return array{
     *     name: ?string,
     *     measurements: array<int, string>,
     *     rows: array<int, array<string, mixed>>,
     *     material: ?array<string, mixed>,
     *     has_chart: bool
     * }
     */
    public function for(Product $product): array
    {
        $chart = $this->chartFor($product);

        $rows = $chart
            ? $chart->rows->map(function (SizeChartRow $row) {
                $out = ['size' => $row->size, 'fit' => $row->fit];

                foreach (SizeChartRow::MEASUREMENTS as $key) {
                    $out[$key] = $row->{$key};
                }

                return $out;
            })->values()->all()
            : [];

        $material = ProductMaterial::query()
            ->where('product_id', $product->id)
            ->first();

        return [
            'name' => $chart?->name,
            'measurements' => SizeChartRow::MEASUREMENTS,
            'rows' => $rows,
            'material' => $material?->only([
                'material', 'composition', 'care_instruction',
                'fabric_weight', 'stretch', 'transparency', 'lining',
            ]),
            'has_chart' => count($rows) > 0,
        ];
    }
}

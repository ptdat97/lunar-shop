<?php

namespace Modules\Catalog\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Core\Models\Product;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\SizeChart;

/**
 * "Size & Fit" on Lunar's own product editor.
 *
 * The panel's product screen is first-party and stays that way; this adds a
 * panel to its sidebar through the slot registry. Neither the chart link nor
 * the material row is a product column, so they are saved here rather than by
 * the panel's own product update.
 */
class ProductSizingController extends Controller
{
    /** Fields on product_materials this panel edits. */
    private const MATERIAL_FIELDS = [
        'material', 'composition', 'stretch', 'transparency',
        'fabric_weight', 'lining', 'care_instruction',
    ];

    /**
     * This product's current sizing, fetched by the slot when it mounts.
     *
     * A slot's props are declared once per request and cannot know which record
     * the page is showing, so the component asks for its own state rather than
     * having the product payload grow fields the panel knows nothing about.
     */
    public function show(Product $product): JsonResponse
    {
        $material = $product->material;

        return response()->json([
            'size_chart_id' => $product->sizeChart()->first()?->id,
            'material' => $material
                ? array_intersect_key($material->toArray(), array_flip(self::MATERIAL_FIELDS))
                : [],
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'size_chart_id' => ['nullable', 'integer', 'exists:size_charts,id'],
            'material.material' => ['nullable', 'string', 'max:255'],
            'material.composition' => ['nullable', 'string', 'max:255'],
            'material.stretch' => ['nullable', 'in:none,slight,stretchy'],
            'material.transparency' => ['nullable', 'in:opaque,semi,sheer'],
            'material.fabric_weight' => ['nullable', 'string', 'max:255'],
            'material.lining' => ['nullable', 'in:none,partial,full'],
            'material.care_instruction' => ['nullable', 'string', 'max:2000'],
        ]);

        // One chart per product: sync replaces whatever was assigned, and an
        // empty list is how the assignment is cleared.
        $product->sizeChart()->sync(
            filled($data['size_chart_id'] ?? null) ? [$data['size_chart_id']] : [],
        );

        $material = array_intersect_key($data['material'] ?? [], array_flip(self::MATERIAL_FIELDS));

        if (collect($material)->filter(fn ($value) => filled($value))->isNotEmpty()) {
            ProductMaterial::updateOrCreate(['product_id' => $product->id], $material);
        } elseif ($product->material) {
            // Every field cleared means the section was emptied on purpose;
            // leaving a row of nulls behind would keep the storefront rendering
            // an empty "Material & care" block.
            $product->material->delete();
        }

        return back()->with('success', __('admin.sizing.title'));
    }

    /**
     * The slot's props: the charts to choose from, this screen's labels and its
     * option lists. Resolved per request, so a chart added a minute ago is
     * pickable without clearing anything.
     *
     * @return array<string, mixed>
     */
    public static function slotProps(): array
    {
        return [
            'charts' => SizeChart::query()
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'labels' => [
                'heading' => __('admin.sizing.title'),
                'chart' => __('admin.sizing.assigned_chart'),
                'chartHelp' => __('admin.sizing.chart_help'),
                'noChart' => __('admin.sizing.no_chart'),
                'materialSection' => __('admin.sizing.material_section'),
                'material' => __('admin.sizing.main_material'),
                'composition' => __('admin.sizing.composition'),
                'stretch' => __('admin.sizing.stretch'),
                'transparency' => __('admin.sizing.transparency'),
                'fabricWeight' => __('admin.sizing.fabric_weight'),
                'lining' => __('admin.sizing.lining'),
                'care' => __('admin.sizing.care'),
                'save' => __('panel.save'),
            ],
            'options' => [
                'stretch' => [
                    'none' => __('admin.sizing.stretch_none'),
                    'slight' => __('admin.sizing.stretch_slight'),
                    'stretchy' => __('admin.sizing.stretch_stretchy'),
                ],
                'transparency' => [
                    'opaque' => __('admin.sizing.trans_opaque'),
                    'semi' => __('admin.sizing.trans_semi'),
                    'sheer' => __('admin.sizing.trans_sheer'),
                ],
                'lining' => [
                    'none' => __('admin.sizing.lining_none'),
                    'partial' => __('admin.sizing.lining_partial'),
                    'full' => __('admin.sizing.lining_full'),
                ],
            ],
        ];
    }
}

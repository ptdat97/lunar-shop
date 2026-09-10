<?php

namespace Modules\Catalog\Panel;

use Illuminate\Database\Eloquent\Builder;
use Modules\Catalog\Models\SizeChart;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;

/**
 * Size charts — the measurement tables behind the storefront's size guide and
 * the fit recommender. Each row is one size; a measurement may be a single
 * number or a "min-max" range, which SizeChartRow::numeric() reduces to a
 * mid-point.
 */
class SizeChartResource extends PanelResource
{
    public function model(): string
    {
        return SizeChart::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Bảng size là dữ liệu của sản phẩm, tra thưa hơn Đánh giá. */
    public function navigationGroup(): string
    {
        return 'catalog';
    }

    public function navigationPriority(): int
    {
        return 70;
    }

    public function key(): string
    {
        return 'size-charts';
    }

    public function label(): string
    {
        return __('admin.size_chart.plural');
    }

    public function singular(): string
    {
        return __('admin.size_chart.label');
    }

    public function icon(): string
    {
        return 'type';
    }

    public function permission(): string
    {
        return 'catalog:manage-products';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('admin.common.name'))
                ->required()->rules('max:255')->placeholder('Áo nữ')->onIndex()->width(6),
            Field::select('category', __('admin.size_chart.category'), [
                'tops' => __('admin.size_chart.cat_tops'),
                'bottoms' => __('admin.size_chart.cat_bottoms'),
                'dresses' => __('admin.size_chart.cat_dresses'),
                'outerwear' => __('admin.size_chart.cat_outerwear'),
                'accessories' => __('admin.size_chart.cat_accessories'),
            ])->onIndex()->width(4),
            Field::toggle('active', __('admin.common.active'))->default(true)->onIndex()->width(2),

            Field::hasMany('rows', __('admin.size_chart.sizes'), [
                Field::text('size', __('admin.size_chart.size'))->required()->placeholder('S')->width(3),
                Field::select('fit', __('admin.size_chart.fit'), [
                    'slim' => __('admin.size_chart.fit_slim'),
                    'regular' => __('admin.size_chart.fit_regular'),
                    'relaxed' => __('admin.size_chart.fit_relaxed'),
                    'oversized' => __('admin.size_chart.fit_oversized'),
                ])->width(3),
                Field::text('bust', __('admin.size_chart.bust'))->width(2),
                Field::text('waist', __('admin.size_chart.waist'))->width(2),
                Field::text('hip', __('admin.size_chart.hip'))->width(2),
                Field::text('shoulder', __('admin.size_chart.shoulder'))->width(2),
                Field::text('length', __('admin.size_chart.length'))->width(2),
                Field::text('inseam', __('admin.size_chart.inseam'))->width(2),
            ])->itemLabel('size')->addLabel(__('admin.size_chart.size'))
                ->help(__('admin.size_chart.sizes_desc')),
        ];
    }

    public function indexQuery(Builder $query): Builder
    {
        return $query->withCount('rows');
    }

    public function computed(): array
    {
        return ['rows_count' => fn (SizeChart $chart) => $chart->rows_count];
    }

    public function computedLabels(): array
    {
        return ['rows_count' => __('admin.size_chart.sizes')];
    }

    public function searchable(): array
    {
        return ['name'];
    }

    public function defaultSort(): array
    {
        return ['name', 'asc'];
    }
}

<?php

namespace Modules\Content\Panel;

use Modules\Content\Models\Banner;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;

/** Storefront hero/promo banners. */
class BannerResource extends PanelResource
{
    public function model(): string
    {
        return Banner::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function key(): string
    {
        return 'banners';
    }

    public function label(): string
    {
        return __('admin.banner.plural');
    }

    public function singular(): string
    {
        return __('admin.banner.label');
    }

    public function icon(): string
    {
        return 'image';
    }

    public function fields(): array
    {
        return [
            Field::text('title', __('admin.common.title'))->required()->rules('max:255')->onIndex()->width(6),
            Field::text('subtitle', __('admin.banner.subtitle'))->rules('max:255')->width(6),
            Field::text('button_text', __('admin.banner.button_text'))->rules('max:255')->width(6),
            Field::text('button_url', __('admin.banner.button_url'))->rules('max:255')->width(6),
            Field::select('position', __('admin.banner.position'), [
                'center' => __('admin.banner.pos_center'),
                'left' => __('admin.banner.pos_left'),
                'right' => __('admin.banner.pos_right'),
            ])->default('center')->onIndex()->width(4),
            Field::number('sort', __('admin.common.sort'))->default(0)->onIndex()->width(4),
            Field::toggle('active', __('admin.common.active'))->default(true)->onIndex()->width(4),
            Field::image('image', __('admin.banner.image'))->help(__('admin.banner.images_pick'))->width(6),
            Field::image('mobile_image', __('admin.banner.mobile_image'))->width(6),
        ];
    }

    public function searchable(): array
    {
        return ['title', 'subtitle'];
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }
}

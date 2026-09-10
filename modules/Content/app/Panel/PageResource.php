<?php

namespace Modules\Content\Panel;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Models\Page;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;

/** Static storefront pages (about, policies, size guide …). */
class PageResource extends PanelResource
{
    public function model(): string
    {
        return Page::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Trang là thứ hay sửa nhất trong nhóm nội dung. */
    public function navigationGroup(): string
    {
        return 'shop-content';
    }

    public function navigationPriority(): int
    {
        return 10;
    }

    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return __('admin.page.plural');
    }

    public function singular(): string
    {
        return __('admin.page.label');
    }

    public function icon(): string
    {
        return 'fileText';
    }

    public function fields(): array
    {
        return [
            Field::text('title', __('admin.common.title'))->required()->rules('max:255')->onIndex()->width(8),
            Field::slug('slug', __('admin.common.slug'), from: 'title')->required()->onIndex()->width(4),
            Field::toggle('published', __('admin.common.published'))->default(false)->onIndex()->width(4),
            Field::image('featured_image', __('admin.page.featured_image'))->help(__('admin.page.featured_pick'))->width(8),
            Field::html('content', __('admin.common.content')),
            Field::text('meta_title', __('admin.common.meta_title'))->rules('max:255')->width(6),
            Field::textarea('meta_description', __('admin.common.meta_description'))->rules('max:500')->width(6),
            Field::json('og_data', __('admin.common.seo')),
        ];
    }

    /** A page is served by slug, so a duplicate would shadow another page. */
    public function validationRules(?Model $record = null, array $input = []): array
    {
        $rules = parent::validationRules($record, $input);
        $rules['slug'][] = $this->unique('slug', $record);

        return $rules;
    }

    public function searchable(): array
    {
        return ['title', 'slug'];
    }

    public function defaultSort(): array
    {
        return ['title', 'asc'];
    }
}

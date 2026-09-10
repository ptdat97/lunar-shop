<?php

namespace Modules\Content\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Models\Product;
use Modules\Content\Models\Lookbook;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;

/**
 * A lookbook: a set of photos plus the products worn in them.
 *
 * Photos and products are real child tables, not JSON — so both repeaters are
 * `hasMany` ones, which keep row ids across a save. That matters here more than
 * anywhere else: an item pinned to a photo references that photo by id, and
 * recreating rows on every save would break every pin.
 */
class LookbookResource extends PanelResource
{
    public function model(): string
    {
        return Lookbook::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Lookbook đổi theo mùa. */
    public function navigationGroup(): string
    {
        return 'shop-content';
    }

    public function navigationPriority(): int
    {
        return 40;
    }

    public function key(): string
    {
        return 'lookbooks';
    }

    public function label(): string
    {
        return __('admin.lookbook.plural');
    }

    public function singular(): string
    {
        return __('admin.lookbook.label');
    }

    public function icon(): string
    {
        return 'image';
    }

    public function fields(): array
    {
        return [
            Field::text('title', __('admin.common.title'))->required()->rules('max:255')->onIndex()->width(8),
            Field::slug('slug', __('admin.common.slug'), from: 'title')->required()->onIndex()->width(4),
            Field::toggle('published', __('admin.common.published'))->default(false)->onIndex()->width(4),
            Field::image('cover_image', __('admin.lookbook.cover'))
                ->help(__('admin.lookbook.cover_pick'))->width(8),
            Field::textarea('description', __('admin.common.description')),

            Field::hasMany('images', __('admin.lookbook.section_gallery'), [
                Field::image('image', __('admin.common.image'))->required()->width(8),
                Field::text('caption', __('admin.lookbook.caption'))->rules('max:255')->width(4),
            ])->itemLabel('caption')->help(__('admin.lookbook.gallery_desc')),

            Field::hasMany('items', __('admin.lookbook.section_products'), [
                Field::relation('product_id', __('admin.lookbook.product'), fn () => Product::get()
                    ->mapWithKeys(fn (Product $p) => [$p->id => $p->translate('name')])
                    ->all())->required()->width(6),
                Field::text('caption', __('admin.lookbook.caption'))->rules('max:255')->width(6),
                // The pin: which photo of THIS lookbook the product sits on, and
                // where. Blank means the product is only in "shop the set".
                Field::relation('image_id', __('admin.lookbook.pin_image'), fn (?Model $record) => $record
                    ? $record->images->mapWithKeys(fn ($image, $index) => [
                        $image->id => $image->caption ?: __('admin.common.image').' #'.($index + 1),
                    ])->all()
                    : [])->width(4),
                Field::number('pos_x', 'X %')->rules('min:0', 'max:100')->width(4),
                Field::number('pos_y', 'Y %')->rules('min:0', 'max:100')->width(4),
            ])->itemLabel('caption')->help(__('admin.lookbook.products_desc')),
        ];
    }

    /** A lookbook is served by slug, so a duplicate would shadow another. */
    public function validationRules(?Model $record = null, array $input = []): array
    {
        $rules = parent::validationRules($record, $input);
        $rules['slug'][] = $this->unique('slug', $record);

        return $rules;
    }

    public function indexQuery(Builder $query): Builder
    {
        return $query->withCount(['images', 'items']);
    }

    public function computed(): array
    {
        return [
            'images_count' => fn (Lookbook $lookbook) => $lookbook->images_count,
            'items_count' => fn (Lookbook $lookbook) => $lookbook->items_count,
        ];
    }

    public function computedLabels(): array
    {
        return [
            'images_count' => __('admin.lookbook.section_gallery'),
            'items_count' => __('admin.lookbook.section_products'),
        ];
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

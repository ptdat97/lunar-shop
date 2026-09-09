<?php

namespace Modules\Content\Panel;

use Lunar\Core\Models\Collection;
use Lunar\Core\Models\Product;
use Modules\Content\Models\PageSection;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Promotion\Services\PromotionService;

/**
 * The storefront's home page, in a table.
 *
 * Each row is one section — a Blade partial in the active theme keyed by
 * `type` — and `settings` is that partial's content. The settings a type takes
 * differ wildly (a hero slider holds slides, a flash sale holds a heading and a
 * count), so the fields below are declared per type and only the branch the
 * chosen type selects is rendered, submitted and validated.
 *
 * Two branches deliberately share the name `settings.slides`: hero-slider and
 * lookbook both store their content there, and the theme partials read that
 * key. They never coexist because `visibleWhen` keeps exactly one live.
 */
class PageSectionResource extends PanelResource
{
    public function model(): string
    {
        return PageSection::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function key(): string
    {
        return 'page-sections';
    }

    public function label(): string
    {
        return __('admin.section.plural');
    }

    public function singular(): string
    {
        return __('admin.section.label');
    }

    public function icon(): string
    {
        return 'sliders';
    }

    public function fields(): array
    {
        return [
            ...$this->identityFields(),
            ...$this->heroSliderFields(),
            ...$this->iconBoxFields(),
            ...$this->lookbookFields(),
            ...$this->collectionGridFields(),
            ...$this->productTabFields(),
            ...$this->flashSaleFields(),
            ...$this->promotionSliderFields(),
        ];
    }

    /** @return array<int, Field> */
    protected function identityFields(): array
    {
        return [
            Field::text('page_handle', __('admin.section.page_handle'))
                ->required()->default('home')->onIndex()->width(4),
            Field::select('type', __('admin.section.type'), PageSection::TYPES)
                ->required()->onIndex()->width(5),
            Field::number('sort', __('admin.common.sort'))->default(0)->onIndex()->width(2),
            Field::toggle('enabled', __('admin.common.enabled'))->default(true)->onIndex()->width(1),
        ];
    }

    /** @return array<int, Field> */
    protected function heroSliderFields(): array
    {
        return [
            Field::repeater('settings.slides', __('admin.section.slides'), [
                Field::image('image', __('admin.common.image'))->help(__('admin.section.hero_image_help')),
                Field::text('title', __('admin.common.title'))->width(6),
                Field::text('subtitle', __('admin.common.subheading'))->width(6),
                Field::text('button_text', __('admin.section.button_text'))->width(6),
                Field::text('button_url', __('admin.section.button_url'))->default('/search')->width(6),
            ])->itemLabel('title')->addLabel(__('admin.section.slide'))->visibleWhen('type', 'hero-slider'),
        ];
    }

    /** @return array<int, Field> */
    protected function iconBoxFields(): array
    {
        return [
            Field::repeater('settings.items', __('admin.section.items'), [
                Field::text('icon', __('admin.section.icon_help'))->default('icon-sealCheck')->width(4),
                Field::text('heading', __('admin.common.heading'))->width(4),
                Field::text('text', __('admin.section.text'))->width(4),
            ])->itemLabel('heading')->addLabel(__('admin.section.item'))->visibleWhen('type', 'iconbox'),
        ];
    }

    /** @return array<int, Field> */
    protected function lookbookFields(): array
    {
        return [
            Field::repeater('settings.slides', __('admin.section.slides'), [
                Field::image('banner', __('admin.section.banner_image')),
                Field::image('pin_image', __('admin.section.pin_image'))->width(6),
                Field::text('position', __('admin.section.pin_position'))
                    ->placeholder('position3 / position5')->width(6),
                Field::text('pin_title', __('admin.section.product_title'))->width(4),
                Field::text('pin_price', __('admin.section.price'))->width(4),
                Field::text('pin_url', __('admin.section.link'))->default('/search')->width(4),
            ])->itemLabel('pin_title')->addLabel(__('admin.section.slide'))->visibleWhen('type', 'lookbook'),
        ];
    }

    /** @return array<int, Field> */
    protected function collectionGridFields(): array
    {
        return [
            Field::text('settings.kicker', __('admin.section.kicker'))
                ->visibleWhen('type', 'collection-grid')->width(6),
            Field::text('settings.heading', __('admin.common.heading'))
                ->visibleWhen('type', 'collection-grid')->width(6),
            Field::repeater('settings.items', __('admin.section.collections'), [
                Field::relation('collection_id', __('admin.section.collection'), fn () => Collection::get()
                    ->mapWithKeys(fn (Collection $c) => [$c->id => $c->translate('name')])
                    ->all())->required()->width(6),
                Field::image('image', __('admin.section.collection_image'))
                    ->help(__('admin.section.collection_image_help'))->width(6),
            ])->addLabel(__('admin.section.add_collection'))->visibleWhen('type', 'collection-grid'),
        ];
    }

    /** @return array<int, Field> */
    protected function productTabFields(): array
    {
        return [
            Field::text('settings.kicker', __('admin.section.kicker'))
                ->visibleWhen('type', 'product-tabs')->width(6),
            Field::text('settings.heading', __('admin.common.heading'))
                ->visibleWhen('type', 'product-tabs')->width(6),
            Field::repeater('settings.tabs', __('admin.section.tabs'), [
                Field::text('label', __('admin.section.tab_label'))->required()->width(4),
                Field::relation('product_ids', __('admin.section.tab_products'), fn () => Product::get()
                    ->mapWithKeys(fn (Product $p) => [$p->id => $p->translate('name')])
                    ->all())->multiple()->help(__('admin.section.tab_products_help'))->width(8),
            ])->itemLabel('label')->addLabel(__('admin.section.add_tab'))->visibleWhen('type', 'product-tabs'),
        ];
    }

    /** @return array<int, Field> */
    protected function flashSaleFields(): array
    {
        return [
            Field::text('settings.heading', __('admin.common.heading'))
                ->help(__('admin.section.flash_sale_heading_help'))
                ->visibleWhen('type', 'flash-sale')->width(6),
            Field::number('settings.limit', __('admin.section.promotion_count'))
                ->rules('min:1', 'max:24')->default(8)
                ->help(__('admin.section.promotion_count_help'))
                ->visibleWhen('type', 'flash-sale')->width(6),
        ];
    }

    /** @return array<int, Field> */
    protected function promotionSliderFields(): array
    {
        return [
            Field::text('settings.subheading', __('admin.common.subheading'))
                ->visibleWhen('type', 'promotion-slider')->width(6),
            Field::number('settings.limit', __('admin.section.promotion_count'))
                ->rules('min:1', 'max:50')->default(12)
                ->help(__('admin.section.promotion_count_help'))
                ->visibleWhen('type', 'promotion-slider')->width(6),
            Field::relation('settings.promotion', __('admin.section.promotion_pin'), fn () => app(PromotionService::class)
                ->displayablePromotions()
                ->mapWithKeys(fn ($d) => [$d->handle => $d->name])
                ->all())->help(__('admin.section.promotion_pin_help'))
                ->visibleWhen('type', 'promotion-slider'),
            // promotion-slider shares its heading field with flash-sale's
            // branch above only by label, not by name — both write
            // settings.heading, and only one branch is ever live.
            Field::text('settings.heading', __('admin.common.heading'))
                ->visibleWhen('type', 'promotion-slider')->width(6),
        ];
    }

    public function searchable(): array
    {
        return ['page_handle', 'type'];
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }
}

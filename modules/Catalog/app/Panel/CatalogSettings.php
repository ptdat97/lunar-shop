<?php

namespace Modules\Catalog\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\SettingsGroup;
use Modules\Core\Support\Settings;

/**
 * Catalogue tuning: how many recommendations to show and for how long, whether
 * reviews publish themselves, and the size of the recently-viewed strip.
 *
 * Three stored groups rather than one, kept as they were: `recommend`,
 * `review` and `recently_viewed` are read by three different services, and
 * merging them into one key would have meant a data migration for no gain.
 */
class CatalogSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'catalog';
    }

    public function label(): string
    {
        return __('admin.catalog_settings.title');
    }

    public function icon(): string
    {
        return 'box';
    }

    public function priority(): int
    {
        return 10;
    }

    public function fields(): array
    {
        return [
            Field::number('recommend.product_limit', __('admin.recommend.product_limit'))
                ->rules('min:1', 'max:48')->default(8)->width(4),
            Field::number('recommend.cart_limit', __('admin.recommend.cart_limit'))
                ->rules('min:1', 'max:48')->default(6)->width(4),
            Field::number('recommend.cache_ttl', __('admin.recommend.cache_ttl'))
                ->rules('min:0')->default(3600)->width(4),
            Field::number('recently_viewed.limit', __('admin.recently_viewed.limit'))
                ->rules('min:1', 'max:48')->default(8)->width(6),
            Field::toggle('review.auto_approve', __('admin.review.auto_approve'))->default(true)->width(6),
        ];
    }

    public function values(): array
    {
        $settings = app(Settings::class);

        return [
            'recommend' => [
                'product_limit' => (int) $settings->get('recommend.product_limit', 8),
                'cart_limit' => (int) $settings->get('recommend.cart_limit', 6),
                'cache_ttl' => (int) $settings->get('recommend.cache_ttl', 3600),
            ],
            'review' => ['auto_approve' => (bool) $settings->get('review.auto_approve', true)],
            'recently_viewed' => ['limit' => (int) $settings->get('recently_viewed.limit', 8)],
        ];
    }

    public function persist(array $data): void
    {
        $settings = app(Settings::class);

        $settings->put('recommend', [
            'product_limit' => (int) data_get($data, 'recommend.product_limit', 8),
            'cart_limit' => (int) data_get($data, 'recommend.cart_limit', 6),
            'cache_ttl' => (int) data_get($data, 'recommend.cache_ttl', 3600),
        ]);

        $settings->put('review', [
            'auto_approve' => (bool) data_get($data, 'review.auto_approve', true),
        ]);

        $settings->put('recently_viewed', [
            'limit' => (int) data_get($data, 'recently_viewed.limit', 8),
        ]);
    }
}

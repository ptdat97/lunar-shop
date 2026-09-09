<?php

namespace Modules\Shipping\Panel;

use Lunar\Core\Models\Country;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Shipping\Models\ShippingZone;

/**
 * Shipping rates by destination. Zones are matched by country, then by the
 * listed provinces, highest priority first — so an overlapping pair is resolved
 * by `priority`, not by insertion order.
 */
class ShippingZoneResource extends PanelResource
{
    public function model(): string
    {
        return ShippingZone::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function key(): string
    {
        return 'shipping-zones';
    }

    public function label(): string
    {
        return __('admin.shipping.plural');
    }

    public function singular(): string
    {
        return __('admin.shipping.label');
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function permission(): string
    {
        return 'settings:core';
    }

    public function fields(): array
    {
        return [
            Field::text('name', __('admin.common.name'))
                ->required()->rules('max:255')->help(__('admin.shipping.name_help'))->onIndex()->width(6),
            Field::relation('country_code', __('admin.shipping.country'), fn () => Country::query()
                ->whereNotNull('iso2')->orderBy('name')->pluck('name', 'iso2')->all())
                ->required()->onIndex()->width(6),
            Field::tags('states', __('admin.shipping.states'))
                ->placeholder(__('admin.shipping.states_placeholder'))
                ->help(__('admin.shipping.states_help')),
            Field::number('rate', __('admin.shipping.rate'))
                ->required()->rules('min:0')->default(3000)
                ->help(__('admin.shipping.rate_help'))->onIndex()->width(4),
            Field::number('free_threshold', __('admin.shipping.free_threshold'))
                ->rules('min:0')->default(0)
                ->help(__('admin.shipping.free_threshold_help'))->width(4),
            Field::number('priority', __('admin.shipping.priority'))->default(0)->onIndex()->width(4),
            Field::toggle('enabled', __('admin.common.enabled'))->default(true)->onIndex(),
        ];
    }

    public function searchable(): array
    {
        return ['name', 'country_code'];
    }

    /** Highest priority first — the order the matcher itself walks them in. */
    public function defaultSort(): array
    {
        return ['priority', 'desc'];
    }
}

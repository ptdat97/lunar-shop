<?php

namespace Modules\Shipping\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;

/** The flat fallback rate, and the pickup address if the shop offers one. */
class ShippingSettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'shipping';
    }

    public function label(): string
    {
        return __('admin.shipping_settings.title');
    }

    public function icon(): string
    {
        return 'truck';
    }

    public function priority(): int
    {
        return 50;
    }

    public function fields(): array
    {
        return [
            Field::number('standard_rate', __('admin.shipping_settings.standard_rate'))
                ->rules('min:0')->default(3000)->width(6),
            Field::number('free_threshold', __('admin.shipping_settings.free_threshold'))
                ->rules('min:0')->default(0)->width(6),

            Field::toggle('pickup_enabled', __('admin.shipping_settings.pickup_enabled'))->default(false),
            // The address only matters when pickup is on, so it stays out of
            // the way until it does.
            Field::text('pickup_name', __('admin.shipping_settings.pickup_name'))
                ->visibleWhen('pickup_enabled', '1', 'true')->width(6),
            Field::text('pickup_line_one', __('admin.shipping_settings.pickup_line_one'))
                ->visibleWhen('pickup_enabled', '1', 'true')->width(6),
            Field::text('pickup_city', __('admin.shipping_settings.pickup_city'))
                ->visibleWhen('pickup_enabled', '1', 'true')->width(6),
            Field::text('pickup_state', __('admin.shipping_settings.pickup_state'))
                ->visibleWhen('pickup_enabled', '1', 'true')->width(6),
            Field::textarea('pickup_hours', __('admin.shipping_settings.pickup_hours'))
                ->visibleWhen('pickup_enabled', '1', 'true')->width(6),
            Field::textarea('pickup_instructions', __('admin.shipping_settings.pickup_instructions'))
                ->visibleWhen('pickup_enabled', '1', 'true')->width(6),
        ];
    }
}

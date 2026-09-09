<?php

namespace Modules\Customer\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;
use Modules\Customer\Services\TokenIssuer;

/** How long a storefront login stays valid. */
class CustomerSettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'customer';
    }

    public function label(): string
    {
        return __('admin.customer_settings.title');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function priority(): int
    {
        return 30;
    }

    public function fields(): array
    {
        return [
            Field::number('ttl_days', __('admin.customer_settings.ttl_days'))
                ->rules('min:1', 'max:365')->default(TokenIssuer::DEFAULT_TTL_DAYS)->width(6),
        ];
    }
}

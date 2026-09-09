<?php

namespace Modules\Promotion\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;
use Modules\Core\Support\Settings;

/**
 * Membership tiers: spend this much, get this discount.
 *
 * Stored under `promotion.membership`, and the tiers are sorted ascending by
 * spend on save — MembershipService walks them in order and takes the last one
 * reached, so an unsorted list would quietly award the wrong tier.
 */
class MembershipSettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'membership';
    }

    protected function storageKey(): string
    {
        return 'promotion';
    }

    public function label(): string
    {
        return __('admin.membership.title');
    }

    public function icon(): string
    {
        return 'userPlus';
    }

    public function priority(): int
    {
        return 60;
    }

    public function fields(): array
    {
        return [
            Field::toggle('enabled', __('admin.membership.enabled'))->default(false),
            Field::repeater('tiers', __('admin.membership.title'), [
                Field::text('handle', __('admin.membership.handle'))->required()->width(3),
                Field::text('name', __('admin.membership.name'))->required()->width(3),
                Field::number('min_spend', __('admin.membership.min_spend'))
                    ->required()->rules('min:0')->default(0)->width(3),
                Field::number('discount_percentage', __('admin.membership.discount_percentage'))
                    ->required()->rules('min:0', 'max:100')->default(0)->width(3),
            ])->itemLabel('name')->addLabel(__('admin.membership.title')),
        ];
    }

    public function values(): array
    {
        $settings = app(Settings::class);

        return [
            'enabled' => (bool) $settings->get('promotion.membership.enabled', false),
            'tiers' => $settings->get('promotion.membership.tiers', []),
        ];
    }

    public function persist(array $data): void
    {
        $tiers = collect($data['tiers'] ?? [])
            ->map(fn (array $tier) => [
                'handle' => (string) ($tier['handle'] ?? ''),
                'name' => (string) ($tier['name'] ?? ''),
                'min_spend' => (int) ($tier['min_spend'] ?? 0),
                'discount_percentage' => (int) ($tier['discount_percentage'] ?? 0),
            ])
            ->sortBy('min_spend')
            ->values()
            ->all();

        app(Settings::class)->put('promotion', [
            'membership' => [
                'enabled' => (bool) ($data['enabled'] ?? false),
                'tiers' => $tiers,
            ],
        ]);
    }
}

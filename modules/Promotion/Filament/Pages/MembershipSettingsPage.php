<?php

namespace Modules\Promotion\Filament\Pages;

use App\Support\Settings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin page for spend-based membership tiers (Silver/Gold …). Stored in
 * app_settings and read via Settings by MembershipService, which maps each tier
 * to a Lunar CustomerGroup and scopes discounts to it.
 */
class MembershipSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $slug = 'settings/membership';

    protected static string $view = 'promotion-admin::filament.membership-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.membership.title');
    }

    public function getTitle(): string
    {
        return __('admin.membership.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $settings = app(Settings::class);

        $this->form->fill([
            'enabled' => (bool) $settings->get('promotion.membership.enabled', false),
            'tiers' => $settings->get('promotion.membership.tiers', []),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.membership.title'))->schema([
                    Toggle::make('enabled')
                        ->label(__('admin.membership.enabled'))
                        ->helperText(__('admin.membership.enabled_help')),
                ]),

                Section::make(__('admin.membership.tiers'))
                    ->description(__('admin.membership.tiers_help'))
                    ->schema([
                        Repeater::make('tiers')
                            ->hiddenLabel()
                            ->addActionLabel(__('admin.membership.add_tier'))
                            ->reorderable()
                            ->columns(4)
                            ->schema([
                                TextInput::make('handle')->label(__('admin.membership.handle'))->required(),
                                TextInput::make('name')->label(__('admin.membership.name'))->required(),
                                TextInput::make('min_spend')->label(__('admin.membership.min_spend'))->numeric()->minValue(0)->required(),
                                TextInput::make('discount_percentage')->label(__('admin.membership.discount_percentage'))->numeric()->minValue(0)->maxValue(100)->required(),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Normalise + sort tiers ascending by min_spend (MembershipService relies
        // on ascending order to pick the highest reached tier).
        $tiers = collect($data['tiers'] ?? [])
            ->map(fn ($t) => [
                'handle' => (string) ($t['handle'] ?? ''),
                'name' => (string) ($t['name'] ?? ''),
                'min_spend' => (int) ($t['min_spend'] ?? 0),
                'discount_percentage' => (int) ($t['discount_percentage'] ?? 0),
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

        Notification::make()->title(__('admin.membership.saved'))->success()->send();
    }
}

<?php

namespace Modules\Customer\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\TokenIssuer;

/**
 * How long a mobile-app sign-in lasts.
 *
 * Token *abilities* are absent on purpose: that is a security scope, and
 * widening it from a web form would be a privilege escalation waiting to happen.
 * It stays in config, where a deploy and a code review guard it.
 */
class CustomerSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $slug = 'settings/customers';

    protected static string $view = 'customer::filament.pages.customer-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.customer_settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.customer_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'ttl_days' => app(TokenIssuer::class)->ttlDays(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.customer_settings.section'))
                    ->schema([
                        TextInput::make('ttl_days')
                            ->label(__('admin.customer_settings.ttl_days'))
                            ->helperText(__('admin.customer_settings.ttl_days_help'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(TokenIssuer::MAX_TTL_DAYS)
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // `put()` replaces the whole group; every key this page owns is written.
        app(Settings::class)->put('customer', [
            'ttl_days' => (int) ($data['ttl_days'] ?? TokenIssuer::DEFAULT_TTL_DAYS),
        ]);

        Notification::make()->title(__('admin.customer_settings.saved'))->success()->send();
    }
}

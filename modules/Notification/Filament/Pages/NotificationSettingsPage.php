<?php

namespace Modules\Notification\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Support\Settings;
use Modules\Notification\Support\PushSettings;

/**
 * Push delivery on/off.
 *
 * Deliberately the only field. Which *driver* sends the push names a PHP class
 * and is resolved before the database is reachable — choosing one that isn't
 * installed would break every request, so it stays in config where a deploy
 * guards it. Turning push off is the decision an operator makes at 2am.
 */
class NotificationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $slug = 'settings/notifications';

    protected static string $view = 'notification::filament.pages.notification-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.notification_settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.notification_settings.title');
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
            'push_enabled' => PushSettings::enabled(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.notification_settings.section'))
                    ->schema([
                        Toggle::make('push_enabled')
                            ->label(__('admin.notification_settings.push_enabled'))
                            ->helperText(__('admin.notification_settings.push_enabled_help')),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // `put()` replaces the whole group; every key this page owns is written.
        app(Settings::class)->put('notification', [
            'push_enabled' => (bool) ($data['push_enabled'] ?? true),
        ]);

        Notification::make()->title(__('admin.notification_settings.saved'))->success()->send();
    }
}

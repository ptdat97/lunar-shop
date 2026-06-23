<?php

namespace Modules\Theme\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Theme\Services\ThemeSettings;

/**
 * Admin page to edit theme content shown in the header/footer (logo, topbar,
 * social, contact, payment, copyright). Storefront reads these via $theme.
 */
class ThemeSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $title = 'Theme Settings';

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    protected static ?string $slug = 'settings/theme';

    protected static string $view = 'theme-admin::filament.theme-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(ThemeSettings::class)->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('General')->columns(2)->schema([
                    FileUpload::make('general.logo')->label('Header logo')
                        ->image()->disk('media')->directory('theme/logo'),
                    FileUpload::make('general.logo_footer')->label('Footer logo')
                        ->image()->disk('media')->directory('theme/logo'),
                    TextInput::make('copyright')->label('Copyright text')->columnSpanFull(),
                ]),

                Section::make('Top bar messages')->schema([
                    Repeater::make('topbar')->label('Slides')->simple(
                        TextInput::make('text')->required(),
                    )->reorderable(),
                ]),

                Section::make('Social links')->schema([
                    Repeater::make('social')->schema([
                        TextInput::make('icon')->placeholder('icon-fb / icon-instagram')->required(),
                        TextInput::make('url')->url()->default('#'),
                    ])->columns(2)->reorderable(),
                ]),

                Section::make('Contact')->columns(3)->schema([
                    TextInput::make('contact.address')->columnSpanFull(),
                    TextInput::make('contact.email')->email(),
                    TextInput::make('contact.phone'),
                    TextInput::make('newsletter.heading')->label('Newsletter heading')->columnSpanFull(),
                ]),

                Section::make('Payment icons')->schema([
                    FileUpload::make('payment')->label('Images')
                        ->image()->multiple()->reorderable()
                        ->disk('media')->directory('theme/payment'),
                ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(ThemeSettings::class);

        foreach (['general', 'topbar', 'social', 'contact', 'newsletter', 'payment'] as $group) {
            if (array_key_exists($group, $data)) {
                $settings->set($group, (array) $data[$group]);
            }
        }

        // Copyright is a scalar stored under its own key.
        if (array_key_exists('copyright', $data)) {
            $settings->setScalar('copyright', (string) $data['copyright']);
        }

        Notification::make()->title('Theme settings saved')->success()->send();
    }
}

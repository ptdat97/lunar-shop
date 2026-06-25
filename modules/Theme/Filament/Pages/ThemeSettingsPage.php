<?php

namespace Modules\Theme\Filament\Pages;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

    public static function getNavigationLabel(): string
    {
        return __('admin.theme.title');
    }

    public function getTitle(): string
    {
        return __('admin.theme.title');
    }

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
                Section::make(__('admin.theme.general'))->columns(2)->schema([
                    FileUpload::make('general.logo')->label(__('admin.theme.logo'))
                        ->image()->disk('media')->directory('theme/logo'),
                    FileUpload::make('general.logo_footer')->label(__('admin.theme.logo_footer'))
                        ->image()->disk('media')->directory('theme/logo'),
                    TextInput::make('copyright')->label(__('admin.theme.copyright'))->columnSpanFull(),
                ]),

                Section::make(__('admin.theme.topbar'))->schema([
                    Repeater::make('topbar')->label(__('admin.theme.topbar_slides'))->simple(
                        TextInput::make('text')->required(),
                    )->reorderable(),
                ]),

                Section::make(__('admin.theme.social'))->schema([
                    Repeater::make('social')->schema([
                        TextInput::make('icon')->label(__('admin.theme.icon'))->placeholder('icon-fb / icon-instagram')->required(),
                        TextInput::make('url')->label(__('admin.common.url'))->url()->default('#'),
                    ])->columns(2)->reorderable(),
                ]),

                Section::make(__('admin.theme.contact'))->columns(3)->schema([
                    TextInput::make('contact.address')->label(__('admin.theme.address'))->columnSpanFull(),
                    TextInput::make('contact.email')->label(__('admin.theme.email'))->email(),
                    TextInput::make('contact.phone')->label(__('admin.theme.phone')),
                    TextInput::make('newsletter.heading')->label(__('admin.theme.newsletter_heading'))->columnSpanFull(),
                ]),

                Section::make(__('admin.theme.payment'))->schema([
                    FileUpload::make('payment')->label(__('admin.theme.payment_images'))
                        ->image()->multiple()->reorderable()
                        ->disk('media')->directory('theme/payment'),
                ]),

                Section::make(__('admin.theme.language'))
                    ->description(__('admin.theme.language_desc'))
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('language.enabled')
                            ->label(__('admin.theme.language_enabled'))
                            ->options(config('theme.locales', ['en' => 'English']))
                            ->columns(2)
                            ->live()
                            ->minItems(1)
                            ->required()
                            ->columnSpanFull(),
                        Select::make('language.default')
                            ->label(__('admin.theme.language_default'))
                            ->options(fn (Get $get) => collect($get('language.enabled') ?: array_keys(config('theme.locales', [])))
                                ->mapWithKeys(fn ($code) => [$code => config("theme.locales.{$code}", strtoupper($code))])
                                ->all())
                            ->required(),
                        Toggle::make('language.show_switcher')
                            ->label(__('admin.theme.language_switcher'))
                            ->helperText(__('admin.theme.language_switcher_help'))
                            ->default(true),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(ThemeSettings::class);

        foreach (['general', 'topbar', 'social', 'contact', 'newsletter', 'payment', 'language'] as $group) {
            if (array_key_exists($group, $data)) {
                $settings->set($group, (array) $data[$group]);
            }
        }

        // Copyright is a scalar stored under its own key.
        if (array_key_exists('copyright', $data)) {
            $settings->setScalar('copyright', (string) $data['copyright']);
        }

        Notification::make()->title(__('admin.theme.saved'))->success()->send();
    }
}

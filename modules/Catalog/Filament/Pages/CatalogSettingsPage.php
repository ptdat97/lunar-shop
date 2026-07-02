<?php

namespace Modules\Catalog\Filament\Pages;

use Modules\Core\Support\Settings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Catalog-level admin settings: product-recommendation tuning (how many items to
 * show + cache TTL), review moderation (auto-approve), and the recently-viewed
 * strip size. Stored in app_settings and read via Settings (falls back to
 * config). The recommendation strategy chain itself stays in config (code-level).
 */
class CatalogSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'settings/catalog';

    protected static string $view = 'catalog-admin::filament.catalog-settings';

    public static function getNavigationLabel(): string
    {
        return __('admin.catalog_settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.catalog_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        // Grouped under "Settings" alongside the other module settings pages
        // (Payment, Shipping, Inventory), not under the Catalog content group.
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $settings = app(Settings::class);

        $this->form->fill([
            'recommend' => [
                'product_limit' => (int) $settings->get('recommend.product_limit', 8),
                'cart_limit' => (int) $settings->get('recommend.cart_limit', 6),
                'cache_ttl' => (int) $settings->get('recommend.cache_ttl', 3600),
            ],
            'review' => [
                'auto_approve' => (bool) $settings->get('review.auto_approve', true),
            ],
            'recently_viewed' => [
                'limit' => (int) $settings->get('recently_viewed.limit', 8),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.recommend.title'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('recommend.product_limit')->label(__('admin.recommend.product_limit'))
                            ->numeric()->minValue(1)->maxValue(24)->required(),
                        TextInput::make('recommend.cart_limit')->label(__('admin.recommend.cart_limit'))
                            ->numeric()->minValue(1)->maxValue(12)->required(),
                        TextInput::make('recommend.cache_ttl')->label(__('admin.recommend.cache_ttl'))
                            ->numeric()->minValue(0)->required(),
                    ]),

                Section::make(__('admin.review.section'))
                    ->schema([
                        Toggle::make('review.auto_approve')
                            ->label(__('admin.review.auto_approve'))
                            ->helperText(__('admin.review.auto_approve_help')),
                    ]),

                Section::make(__('admin.recently_viewed.section'))
                    ->schema([
                        TextInput::make('recently_viewed.limit')
                            ->label(__('admin.recently_viewed.limit'))
                            ->helperText(__('admin.recently_viewed.limit_help'))
                            ->numeric()->minValue(1)->maxValue(12)->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(Settings::class);

        $settings->put('recommend', [
            'product_limit' => (int) ($data['recommend']['product_limit'] ?? 8),
            'cart_limit' => (int) ($data['recommend']['cart_limit'] ?? 6),
            'cache_ttl' => (int) ($data['recommend']['cache_ttl'] ?? 3600),
        ]);

        $settings->put('review', [
            'auto_approve' => (bool) ($data['review']['auto_approve'] ?? true),
        ]);

        $settings->put('recently_viewed', [
            'limit' => (int) ($data['recently_viewed']['limit'] ?? 8),
        ]);

        Notification::make()->title(__('admin.catalog_settings.saved'))->success()->send();
    }
}

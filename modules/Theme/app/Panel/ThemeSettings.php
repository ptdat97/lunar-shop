<?php

namespace Modules\Theme\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\SettingsGroup;
use Modules\Theme\Services\ThemeSettings as Store;

/**
 * Storefront branding: logos, top-bar ticker, social links, footer contact,
 * newsletter copy, locales and analytics pixel ids.
 *
 * Its own table (`theme_settings`) rather than `app_settings` — the theme reads
 * it on every page render and has its own cache around it, and moving the data
 * would have gained nothing.
 */
class ThemeSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'theme';
    }

    public function label(): string
    {
        return __('admin.theme.title');
    }

    public function icon(): string
    {
        return 'palette';
    }

    public function priority(): int
    {
        return 80;
    }

    public function fields(): array
    {
        return [
            Field::image('general.logo', __('admin.theme.logo'))->width(4),
            Field::image('general.logo_footer', __('admin.theme.logo_footer'))->width(4),
            Field::image('general.favicon', 'Favicon')->width(4),
            Field::text('copyright', __('admin.theme.copyright')),

            Field::image('brand.email_logo', __('admin.theme.email_logo'))->width(6),
            Field::text('brand.email_accent', __('admin.theme.email_accent'))
                ->placeholder('#18181b')->width(6),

            Field::repeater('topbar', __('admin.theme.topbar_slides'), [
                Field::text('text', __('admin.common.content'))->required(),
            ])->itemLabel('text'),

            Field::repeater('social', __('admin.theme.icon'), [
                Field::text('icon', __('admin.theme.icon'))
                    ->required()->placeholder('icon-fb / icon-tiktok')->width(6),
                Field::text('url', __('admin.common.url'))->default('#')->width(6),
            ])->itemLabel('icon'),

            Field::text('contact.address', __('admin.theme.address')),
            Field::text('contact.email', __('admin.theme.email'))->rules('email')->width(6),
            Field::text('contact.phone', __('admin.theme.phone'))->width(6),
            Field::text('newsletter.heading', __('admin.theme.newsletter_heading')),
            // A flat list of image paths for the footer's payment badges. The
            // old admin had a multi-image picker here; until the media library
            // is back on the panel this is the same data, typed.
            Field::tags('payment', __('admin.theme.payment_images')),

            Field::select('language.enabled', __('admin.theme.language_enabled'), self::locales())
                ->multiple()->width(6),
            Field::select('language.default', __('admin.theme.language_default'), self::locales())->width(6),
            Field::toggle('language.show_switcher', __('admin.theme.language_switcher')),

            Field::text('pixels.google', __('admin.theme.google_pixel_id'))->width(6),
            Field::text('pixels.facebook', __('admin.theme.facebook_pixel_id'))->width(6),
        ];
    }

    /** @return array<string, string> */
    protected static function locales(): array
    {
        return (array) config('theme.locales', ['en' => 'English']);
    }

    public function values(): array
    {
        return app(Store::class)->all();
    }

    public function persist(array $data): void
    {
        $store = app(Store::class);

        foreach (['general', 'brand', 'topbar', 'social', 'contact', 'newsletter', 'payment', 'language', 'pixels'] as $group) {
            if (array_key_exists($group, $data)) {
                $store->set($group, (array) $data[$group]);
            }
        }

        // Copyright is a scalar under its own key, not part of a group.
        if (array_key_exists('copyright', $data)) {
            $store->setScalar('copyright', (string) ($data['copyright'] ?? ''));
        }
    }
}

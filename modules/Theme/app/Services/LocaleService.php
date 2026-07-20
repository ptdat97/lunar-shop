<?php

namespace Modules\Theme\Services;

use Illuminate\Http\Request;

/**
 * Storefront locale resolution + persistence. The set of available locales,
 * the default, and whether to show the switcher are admin-configurable via
 * Theme settings (theme_settings → `language`); labels come from
 * config('theme.locales'). One source of truth for middleware/switcher/views
 * (no locale logic in views — coding standards §7).
 */
class LocaleService
{
    public function __construct(protected ThemeSettings $settings) {}

    /** All locales known to the theme (code => label), from config. */
    protected function known(): array
    {
        return (array) config('theme.locales', ['en' => 'English']);
    }

    /** The admin-configured `language` settings group. */
    protected function config(): array
    {
        return (array) $this->settings->get('language', []);
    }

    /**
     * Available locales for the storefront (code => label): the admin-enabled
     * subset of the known locales. Falls back to all known if none configured.
     *
     * @return array<string, string>
     */
    public function supported(): array
    {
        $known = $this->known();
        $enabled = array_filter((array) ($this->config()['enabled'] ?? []));

        if (empty($enabled)) {
            return $known;
        }

        // Preserve known order + labels; keep only enabled + valid codes.
        return array_intersect_key($known, array_flip($enabled)) ?: $known;
    }

    /** Locale codes only. @return list<string> */
    public function codes(): array
    {
        return array_keys($this->supported());
    }

    /** The admin-configured default locale (must be enabled), else the first. */
    public function default(): string
    {
        $default = (string) ($this->config()['default'] ?? '');

        return $this->isSupported($default) ? $default : ($this->codes()[0] ?? 'en');
    }

    /**
     * Whether to render the language switcher. Off when the admin disabled it,
     * or when only one locale is enabled (nothing to switch to).
     */
    public function showSwitcher(): bool
    {
        $show = $this->config()['show_switcher'] ?? true;

        return (bool) $show && count($this->supported()) > 1;
    }

    public function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->codes(), true);
    }

    public function label(string $locale): string
    {
        return $this->supported()[$locale] ?? strtoupper($locale);
    }

    /**
     * The locale to use for this request: session choice → browser preference →
     * default. Only ever returns a supported code.
     */
    public function resolve(Request $request): string
    {
        $session = $request->hasSession() ? $request->session()->get('locale') : null;

        if ($this->isSupported($session)) {
            return $session;
        }

        $preferred = $request->getPreferredLanguage($this->codes());

        return $this->isSupported($preferred) ? $preferred : $this->default();
    }

    /**
     * The locale for a stateless API request: explicit `?locale=` query →
     * `Accept-Language` header → default. No session (API is token/cookie
     * stateless), so a client picks the language per request. Only ever
     * returns a supported code.
     */
    public function resolveForApi(Request $request): string
    {
        $query = $request->query('locale');

        if (is_string($query) && $this->isSupported($query)) {
            return $query;
        }

        $preferred = $request->getPreferredLanguage($this->codes());

        return $this->isSupported($preferred) ? $preferred : $this->default();
    }

    /** Persist a chosen locale to the session (ignored if unsupported). */
    public function store(Request $request, string $locale): bool
    {
        if (! $this->isSupported($locale)) {
            return false;
        }

        $request->session()->put('locale', $locale);

        return true;
    }
}

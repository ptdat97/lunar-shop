<?php

namespace Modules\SectionBuilder\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;
use Modules\SectionBuilder\Models\PageSection;

/**
 * Renders a page's sections (from the DB) into HTML. Each section `type` maps to
 * a Blade view (default `theme::sections.{type}`) plus an optional data provider.
 *
 * A type registry lets modules AND plugins contribute new section types — with
 * their own view and/or data — without editing this renderer or shipping a theme
 * partial. Backward-compatible: types without an explicit registration still
 * resolve to `theme::sections.{type}` as before, and the old provide() API keeps
 * working. The `section.render` filter lets a plugin post-process the HTML.
 */
class SectionRenderer
{
    /**
     * Registered types: type => ['view' => ?string, 'provider' => ?callable].
     * A type need not be registered — unknown types fall back to the theme
     * partial convention.
     *
     * @var array<string, array{view: ?string, provider: ?callable}>
     */
    protected array $types = [];

    /**
     * Register a section type with an optional custom view and/or data provider.
     * This is how a plugin adds a brand-new section type (its own view) that the
     * active theme doesn't ship a partial for.
     *
     * @param  string  $type      the section type handle (matches PageSection.type)
     * @param  ?string  $view     Blade view name; null = theme::sections.{type}
     * @param  ?callable  $provider  fn(array $settings): array<string,mixed> view data
     */
    public function registerType(string $type, ?string $view = null, ?callable $provider = null): void
    {
        $this->types[$type] = [
            'view' => $view,
            'provider' => $provider ?? ($this->types[$type]['provider'] ?? null),
        ];
    }

    /**
     * Register a data provider for a section type (backward-compatible API).
     * Equivalent to registerType($type, view: existing, provider: $provider).
     */
    public function provide(string $type, callable $provider): void
    {
        $this->registerType($type, $this->types[$type]['view'] ?? null, $provider);
    }

    /**
     * Render every enabled section for a page handle, in order.
     */
    public function render(string $pageHandle = 'home'): HtmlString
    {
        $html = PageSection::forPageHandle($pageHandle)->get()
            ->map(fn (PageSection $section) => $this->renderSection($section))
            ->implode("\n");

        return new HtmlString($html);
    }

    protected function renderSection(PageSection $section): string
    {
        $type = $section->type;
        $view = $this->types[$type]['view'] ?? "theme::sections.{$type}";

        if (! View::exists($view)) {
            $html = "<!-- section: missing partial [{$type}] -->";

            return $this->filter($html, $section);
        }

        $settings = $section->settings ?? [];
        $data = ['settings' => $settings, 'section' => $section];

        $provider = $this->types[$type]['provider'] ?? null;

        if ($provider) {
            $data += $provider($settings);
        }

        return $this->filter(View::make($view, $data)->render(), $section);
    }

    /**
     * Let a plugin post-process a section's rendered HTML (wrap, inject, replace)
     * via the section.render filter. Pass-through when no listener.
     */
    protected function filter(string $html, PageSection $section): string
    {
        return Hook::applyFilters(Hooks::SECTION_RENDER, $html, [$section]);
    }
}

<?php

namespace Modules\Content\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Modules\Content\Models\PageSection;

/**
 * Renders a page's sections (from the DB) into HTML by mapping each section
 * `type` to a Blade partial in the active theme (theme::sections.{type}).
 *
 * Dynamic sections register a data provider (provide()) that returns view data
 * pulled from module services, so the Blade partial stays presentation-only.
 */
class SectionRenderer
{
    /** @var array<string, callable> type => fn(array $settings): array view data */
    protected array $providers = [];

    /**
     * Register a data provider for a section type.
     */
    public function provide(string $type, callable $provider): void
    {
        $this->providers[$type] = $provider;
    }

    /**
     * Render every enabled section for a page handle, in order.
     */
    public function render(string $pageHandle = 'home'): HtmlString
    {
        $html = $this->sections($pageHandle)
            ->map(fn (PageSection $section) => $this->renderSection($section))
            ->implode("\n");

        return new HtmlString($html);
    }

    /**
     * The page's section CONFIG (type/sort/settings rows), cached forever and
     * busted by PageSection model events on any admin change. Only the config
     * is cached — each section's DATA (products, prices, media) still comes
     * live from its provider on every request.
     *
     * Raw attribute rows are cached (not model instances) and rehydrated, so
     * the cache payload stays driver-agnostic and casts run normally.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PageSection>
     */
    protected function sections(string $pageHandle): \Illuminate\Database\Eloquent\Collection
    {
        $rows = Cache::rememberForever(
            PageSection::cacheKey($pageHandle),
            fn () => PageSection::forPageHandle($pageHandle)->get()
                ->map(fn (PageSection $s) => $s->getAttributes())
                ->all(),
        );

        return PageSection::hydrate($rows);
    }

    protected function renderSection(PageSection $section): string
    {
        $view = "theme::sections.{$section->type}";

        if (! View::exists($view)) {
            return "<!-- section: missing partial [{$section->type}] -->";
        }

        $settings = $section->settings ?? [];
        $data = ['settings' => $settings, 'section' => $section];

        if (isset($this->providers[$section->type])) {
            $data += ($this->providers[$section->type])($settings);
        }

        return View::make($view, $data)->render();
    }
}

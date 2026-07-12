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
 *
 * A section may ALSO register a serializer (serialize()) turning that same view
 * data into a JSON-safe array for {@see self::payload()} — the headless feed.
 * The two paths share one provider, so a section can never drift between web
 * and API. A dynamic section's view data holds Eloquent models (Product,
 * Discount); the serializer is what maps them through an API Resource. Without
 * one, a dynamic section is OMITTED from the payload rather than serialised
 * raw — an unmapped model would leak its internals into the public contract.
 */
class SectionRenderer
{
    /** @var array<string, callable> type => fn(array $settings): array view data */
    protected array $providers = [];

    /** @var array<string, callable> type => fn(array $data, array $settings): array JSON payload */
    protected array $serializers = [];

    /**
     * Register a data provider for a section type.
     */
    public function provide(string $type, callable $provider): void
    {
        $this->providers[$type] = $provider;
    }

    /**
     * Register how a dynamic section's view data becomes JSON. Receives the
     * provider's output (Eloquent models and all) plus the section settings.
     */
    public function serialize(string $type, callable $serializer): void
    {
        $this->serializers[$type] = $serializer;
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
     * The same page as JSON: an ordered list of `{type, settings, data}` for a
     * headless client to render. Mirrors render() — same sections, same order,
     * same providers — but never emits HTML or an Eloquent model.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payload(string $pageHandle = 'home'): array
    {
        return $this->sections($pageHandle)
            ->map(fn (PageSection $section) => $this->sectionPayload($section))
            ->filter()
            ->values()
            ->all();
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

    /**
     * One section as JSON, or null to omit it.
     *
     * A section with a provider but no serializer is DROPPED: its view data
     * carries Eloquent models, and shipping those unmapped would leak model
     * internals into a public contract we then have to keep. Silence is the
     * safe default — a new dynamic section shows up in the feed only once
     * someone has said what its JSON looks like.
     *
     * @return array<string, mixed>|null
     */
    protected function sectionPayload(PageSection $section): ?array
    {
        $type = $section->type;
        $settings = $section->settings ?? [];
        $hasProvider = isset($this->providers[$type]);
        $hasSerializer = isset($this->serializers[$type]);

        if ($hasProvider && ! $hasSerializer) {
            return null;
        }

        // Static section (hero-slider, iconbox, lookbook): its settings ARE the
        // content — admin-authored strings and image paths, already JSON-safe.
        //
        // A serializer without a provider is legitimate: `promotions-strip`
        // feeds Blade from a view composer, which no JSON response ever runs,
        // so its serializer sources the data itself and gets an empty $data.
        $data = $hasSerializer
            ? ($this->serializers[$type])(
                $hasProvider ? ($this->providers[$type])($settings) : [],
                $settings,
            )
            : [];

        return [
            'type' => $type,
            'settings' => $settings,
            'data' => $data,
        ];
    }
}

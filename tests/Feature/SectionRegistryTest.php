<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;
use Modules\SectionBuilder\Models\PageSection;
use Modules\SectionBuilder\Services\SectionRenderer;
use Tests\TestCase;

/**
 * E.1 — SectionBuilder type registry + section.render filter. A plugin can add a
 * brand-new section type with its OWN view and data provider (no theme partial,
 * no renderer edit), and post-process any section's HTML via the filter.
 * Backward-compatible: unregistered types still resolve to theme::sections.{type}.
 */
class SectionRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stand in for a plugin shipping its own section views.
        View::addNamespace('plugintest', base_path('tests/Fixtures/views'));
    }

    public function test_a_registered_type_uses_its_own_view_and_provider(): void
    {
        $renderer = app(SectionRenderer::class);

        $renderer->registerType(
            'custom-banner',
            view: 'plugintest::custom-banner',
            provider: fn (array $settings) => ['extra' => 'from-provider'],
        );

        PageSection::create([
            'page_handle' => 'plugin-test-page',
            'type' => 'custom-banner',
            'sort' => 0,
            'enabled' => true,
            'settings' => ['heading' => 'Hello'],
        ]);

        $html = (string) $renderer->render('plugin-test-page');

        $this->assertStringContainsString('custom-banner', $html);
        $this->assertStringContainsString('Hello — from-provider', $html);
    }

    public function test_section_render_filter_post_processes_html(): void
    {
        $renderer = app(SectionRenderer::class);
        $renderer->registerType('custom-banner', view: 'plugintest::custom-banner');

        Hook::addFilter(Hooks::SECTION_RENDER, fn (string $html, $section) => $html.'<!-- audited -->');

        PageSection::create([
            'page_handle' => 'plugin-test-page-2',
            'type' => 'custom-banner',
            'sort' => 0,
            'enabled' => true,
            'settings' => [],
        ]);

        $this->assertStringContainsString('<!-- audited -->', (string) $renderer->render('plugin-test-page-2'));
    }

    public function test_unregistered_type_still_uses_the_theme_partial_convention(): void
    {
        // No registration → falls back to theme::sections.{type}; a non-existent
        // one yields the "missing partial" marker (unchanged legacy behaviour).
        PageSection::create([
            'page_handle' => 'plugin-test-page-3',
            'type' => 'does-not-exist',
            'sort' => 0,
            'enabled' => true,
            'settings' => [],
        ]);

        $this->assertStringContainsString(
            'missing partial [does-not-exist]',
            (string) app(SectionRenderer::class)->render('plugin-test-page-3'),
        );
    }
}
